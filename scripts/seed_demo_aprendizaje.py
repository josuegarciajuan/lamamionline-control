#!/usr/bin/env python3
"""Seed demo data — 12 authentic conversations. Messy, real, varied."""

import json, os, random, re
from datetime import datetime, timedelta, timezone
from collections import defaultdict

random.seed(42)

USER_ID = 6
ROOT = os.path.join(os.path.dirname(__file__), '..', 'bot-casa')
DATA = os.path.join(ROOT, 'data', 'users', str(USER_ID))
os.makedirs(DATA, exist_ok=True)

# ── Load lines ───────────────────────────────────────────────────────────
LINES_JSON = os.path.join(DATA, 'lines.json')
LINE_LAST9 = {}; LINE_LABELS = []
if os.path.exists(LINES_JSON):
    for ln in json.load(open(LINES_JSON)):
        l, l9 = ln.get('label',''), ln.get('last9','')
        if l and l9: LINE_LABELS.append(l); LINE_LAST9[l] = l9
if not LINE_LABELS:
    LINE_LABELS = ['Principal', 'Secundaria']
    LINE_LAST9 = {'Principal': '612345678', 'Secundaria': '623456789'}

# ── Load girl photos ─────────────────────────────────────────────────────
GIRLS_JSON = os.path.join(DATA, 'girls.json')
GIRL_PHOTOS = {}
try:
    for g in json.load(open(GIRLS_JSON)).get('girls',[]):
        if g.get('activa') and g.get('fotos'):
            GIRL_PHOTOS[g['nombre']] = g['fotos']
except: pass

GIRLS = list(GIRL_PHOTOS.keys()) or ['Valentina','Kiara','Sandra','Iris']

def photos(girl, n=2, prefix=""):
    """Return absolute photo URLs. Chat.js detects image-proxy URLs as inline images."""
    urls = GIRL_PHOTOS.get(girl, [])
    result = []
    for i in range(min(n, len(urls))):
        # Make relative URLs absolute so chat.js detects them
        url = urls[i]
        if url.startswith('/'):
            url = f"https://admin.casawasap.com{url}"
        if i == 0 and prefix:
            result.append(f"{prefix}\n{url}")
        else:
            result.append(url)
    return result

# ── Config ───────────────────────────────────────────────────────────────
BASE   = datetime(2026, 6, 10, 12, 0, 0, tzinfo=timezone.utc)
MAPS   = "https://maps.app.goo.gl/demo"

# ── Builders ─────────────────────────────────────────────────────────────
_pc = [0]
def mph(): _pc[0] += 1; return f"346{_pc[0]:08d}"
def tid(ln, p): return f"{LINE_LAST9.get(ln, ln)}_{p}"
def ts(ago_h=0, add_m=0): return (BASE - timedelta(hours=ago_h, minutes=-add_m)).isoformat()

def build(msgs, outcome, tags=None, cf=0.80, hok=False, arr=False,
          ago_h=0.0, line=None, girl=None):
    """msgs: list of (user_msg, bot_reply_or_None, add_min, girl_or_None)
       bot_reply can be a string or a list of strings (multiple consecutive bot messages)."""
    ln = line or random.choice(LINE_LABELS)
    pn = mph(); th = tid(ln, pn)
    recs = []; lead = None; g = girl
    start = last_ts = None; empty = 0

    for i, (u, b, add_m, gx) in enumerate(msgs):
        t = ts(ago_h, add_m)
        if i == 0: start = t
        last_ts = t
        if gx: g = gx
        if not g and i == len(msgs)-1: g = random.choice(GIRLS)
        spk = g or (random.choice(GIRLS) if i > 0 else GIRLS[0])

        # Handle multi-message bot replies (list)
        if isinstance(b, list):
            for j, br in enumerate(b):
                pending = (br is None or br == "")
                if pending: empty += 1
                recs.append({"ts":t,"thread_id":th,"phone":pn,
                             "user_msg":u if j==0 else "",
                             "bot_reply":br or "",
                             "speaker_girl_name":spk,"selected_girl_name":gx or "",
                             "sender_lid":pn,"_pending":pending})
            continue

        pending = (b is None or b == "")
        if pending: empty += 1
        recs.append({"ts":t,"thread_id":th,"phone":pn,
                     "user_msg":u,"bot_reply":b or "",
                     "speaker_girl_name":spk,"selected_girl_name":gx or "",
                     "sender_lid":pn,"_pending":pending})

        if outcome.startswith('lead_') and lead is None and g:
            kw = ['min','minuto','ahora','voy','llego','cerca','saliendo','salgo','camino']
            if any(k in u.lower() for k in kw):
                m = re.search(r'(\d+)\s*min', u.lower())
                eta = int(m.group(1)) if m else 15
                lead = {"ts":t,"phone":pn,"eta_minutes":eta,
                        "lead_confidence":cf,"thread_id":th,"line_label":ln,
                        "waha_port":3020 if ln=='Principal' else 3021,
                        "waha_base_url":f"http://100.117.92.74:{3020 if ln=='Principal' else 3021}",
                        "waha_session":"default","user_message":u,
                        "bot_reply":(b if isinstance(b,str) else (b[0] if isinstance(b,list) and b else "")),
                        "selected_girl_name":g or "",
                        "last_followup_ts":None,"arrived":arr}

    # Ensure girl is set for outcome
    if not g:
        for u, b, add_m, gx in reversed(msgs):
            if gx: g = gx; break
        if not g: g = random.choice(GIRLS)

    out = {"thread_id":th,"phone":pn,"started":start,"last_activity":last_ts,
           "message_count":len(recs),"selected_girl":g or "",
           "outcome":outcome,"confidence":round(cf+random.uniform(-.04,.04),2),
           "classified_by":"auto",
           "classified_at":(BASE-timedelta(hours=ago_h-random.uniform(.5,2))).isoformat(),
           "human_confirmed":hok,"tags":tags or [],"bot_empty_replies":empty}
    return recs, out, lead


# ═══════════════════════════════════════════════════════════════════════════
#  12 AUTHENTIC CONVERSATIONS
# ═══════════════════════════════════════════════════════════════════════════
# Based on real bot-casa conversation patterns.
# Bot tone: "hola guapo 😘", "dale", "si amor", short replies, not forced.

# ── 1. LEAD CONFIRMADO — Flujo completo natural (Valentina) ─────────────
# Client: casual, friendly. Bot: warm, sends photo, maps at right time.
P1 = photos('Valentina',2)
C01 = [
    ("hola mi amor",       "holaaa guapo 😘 q tal?", 0, None),
    ("bienn vos",          "aca esperandote jeje, tienes ganas de un ratito?", 1, None),
    ("si la verdad q si\nq precio tenes?", "mira guapo: 40€ rapidito, 50€ media hora, 100€ la horita completa con besos y todo 💋", 2, None),
    ("la media hora esta bien", "perfecto cielo 😘 q chica quieres? tengo a Valentina, Kiara, Sandra e Iris", 3, None),
    ("valentina",          P1, 4, "Valentina"),  # bot sends 2 photos
    ("ufff q hermosa\ndnd es?", "en el centro cielo! me confirmas la media hora con Valentina y te mando el maps 😘", 5, "Valentina"),
    ("si media hora con valentina", f"daleee aqui tienes 🗺️ {MAPS}1\nllegas y me avisas guapo", 6, "Valentina"),
    ("estoy saliendo ya, llego en 10", "perfecto te esperamos bb 😘", 16, "Valentina"),
    ("toca el timbre", None, 26, "Valentina"),  # typing!
]

# ── 2. LEAD CONFIRMADO — "Quien esta libre" (Kiara) ─────────────────────
# Client: direct. Bot: sends 3 photos in a row, short confirmations.
P2a = photos('Kiara',1); P2b = photos('Sandra',1); P2c = photos('Iris',1)
C02 = [
    ("quien esta libre ahora", "holaaa! 😘", 0, None),
    ("",                      "ahorita estan Kiara, Sandra e Iris guapo", 0, None),
    ("manda fotos de las 3",  ["Kiara 🔥", P2a[0], "Sandra 😍", P2b[0], "Iris 💕", P2c[0]], 1, None),
    ("kiara",                 "buena eleccion 😍 Kiara es una diosa", 2, "Kiara"),
    ("precio?",               "40€ rapidito, 50€ media hora, 100€ la hora completa con Kiara 😘", 3, "Kiara"),
    ("media\ndame direccion", "claro amor ❤️ me confirmas media hora con Kiara y te paso el maps", 4, "Kiara"),
    ("si si media hora con kiara", f"dale 😘 {MAPS}2", 5, "Kiara"),
    ("voy en camino 15 min",  "ok bebe te vemos en 15 🫦", 6, "Kiara"),
    ("llego",                 None, 21, "Kiara"),  # typing!
]

# ── 3. LEAD PROBABLE — Mil preguntas, catálogo entero (Sandra) ──────────
P3a = photos('Valentina',1); P3b = photos('Kiara',1)
P3c = photos('Sandra',1); P3d = photos('Iris',1)
C03 = [
    ("q tal el dia",         "bien cielo y tu? todo tranqui por aca esperando un papi como tu 😏", 0, None),
    ("jajaja\nq precios manejan", "40€ rapidito, 50€ media hora, 100€ la hora entera bebe", 1, None),
    ("y q incluye la hora",  "besos, caricias, todo lo rico q te imagines sin prisas 😘", 2, None),
    ("mmm y chicas?",        "mira te paso a todas 😍", 3, None),
    ("",                     ["Valentina ❤️", P3a[0], "Kiara 🔥", P3b[0], "Sandra 😘", P3c[0], "Iris 💕", P3d[0]], 3, None),
    ("sandra me gusta",      f"buena eleccion! Sandra es un amor. 50€ media hora, 100€ la hora. {photos('Sandra',1)[0]}", 4, "Sandra"),
    ("y el lugar es limpio?", "si cielo todo impecable y discreto. en el centro, zona tranquila 😘", 5, "Sandra"),
    ("ok sandra media hora\ndonde es?", f"te paso el maps 😘\n{MAPS}3", 6, "Sandra"),
    ("lo miro y te digo",    "dale guapo cuando quieras 😘", 7, "Sandra"),
]

# ── 4. LEAD PROBABLE — Primera persona, "te vi en un anuncio" (Iris) ─────
# Client addresses Iris directly → bot speaks in 1st person as Iris.
P4 = photos('Iris',2)
C04 = [
    ("Hola Iris, te he visto en un anuncio, estas disponible?",
     "holaaa si soy yo! 😍 disponible ahora mismo", 0, "Iris"),
    ("q tal? q servicios haces?", "de todo cari, frances natural, completo, lo q quieras. 50€ media hora, 100€ la hora 😏", 1, "Iris"),
    ("y tienes mas fotos tuyas?", P4, 2, "Iris"),
    ("q guapa eres, media hora", "ayy gracias papi! 50€ la media hora. quieres venir ahora? dime y te paso la ubicacion 😘", 3, "Iris"),
    ("si, donde estas?",      f"en el centro cielo, zona discreta y tranquila. te paso el maps y me dices cuanto tardas 😘\n{MAPS}4", 4, "Iris"),
    ("voy, 10 min en coche",  "perfecto papi te espero! avisa al llegar 😘", 5, "Iris"),
]

# ── 5. LEAD PROBABLE — Repite mensajes, fallo WA (Valentina) ────────────
P5 = photos('Valentina',1)
C05 = [
    ("Cariño donde vives",   "en el centro cielo! dime con q chica querias estar y te mando el maps 😘", 0, None),
    ("Cariño donde vives",   None, 0, None),  # repeated
    ("Cariño donde vives",   "jajaja se volvio loco el whatsapp? 😂 en el centro bebe, dime q chica te gusta", 0, None),
    ("ay perdon no se q paso jajaja\nvalentina", [P5[0], "22 añitos guapo"], 1, "Valentina"),
    ("parece mentira\nok media hora", "confirmame media hora con Valentina cielo y te paso el maps", 2, "Valentina"),
    ("si confirmo",          f"{MAPS}5", 3, "Valentina"),
    ("gracias ya voy",       "😘", 4, "Valentina"),
]

# ── 6. LEAD GHOSTED — Bonito pero desaparece (Kiara) ───────────────────
P6 = photos('Kiara',3)
C06 = [
    ("Hola buenas tardes, me podria dar informacion por favor?",
     "holaaa claro q si guapo! 40€ rapidito, 50€ media hora y 100€ la hora 😘", 0, None),
    ("Muy bien, que chicas hay ahora?",
     "ahorita estan Kiara, Sandra y Valentina disponibles", 1, None),
    ("Me podria mostrar fotos de Kiara?", P6, 2, "Kiara"),  # 3 photos
    ("Hermosa, me interesa la media hora",
     "perfecto bb media hora con Kiara son 50€, te paso el maps?", 3, "Kiara"),
    ("Si por favor\nes por el centro no?", f"si cielo, en el centro, zona discreta 😘\n{MAPS}6\nllegas y me dices bebe 😘", 4, "Kiara"),
    # ghosted
]

# ── 7. LEAD GHOSTED — Monosílabo, "ok ok ok" (Sandra) ──────────────────
P7 = photos('Sandra',1)
C07 = [
    ("info",                 "hola guapo! 40€ rapidito, 50€ media hora, 100€ la hora completa. dime cual te interesa", 0, None),
    ("ok\nchicas?",          "Sandra, Kiara, Iris y Valentina disponibles 😘", 1, None),
    ("sandra",               P7, 2, "Sandra"),
    ("ok",                   "te gusta cielo? quieres media hora o la horita?", 3, "Sandra"),
    ("50",                   "dale media hora con Sandra, te paso el maps? 😘", 4, "Sandra"),
    ("ok\ndonde es?",        "en el centro cielo, zona tranquila. confirmame y te paso la ubicacion exacta 😘", 5, "Sandra"),
    ("ok",                   None, 6, "Sandra"),
    # ghosted
]

# ── 8. MAREADOR — Todas, negocia, nunca decide ─────────────────────────
P8a = photos('Valentina',1); P8b = photos('Kiara',1)
P8c = photos('Sandra',1); P8d = photos('Iris',1)
C08 = [
    ("quiero ver a todas",   "hola guapo! te paso las fotos 😘", 0, None),
    ("",                     ["Valentina ❤️", P8a[0], "Kiara 🔥", P8b[0], "Sandra 😍", P8c[0], "Iris 💕", P8d[0]], 0, None),
    ("no tenes mas fotos de valentina", [photos('Valentina',1)[0]], 1, None),
    ("y la de pelo negro como se llama", "Kiara bebe! 😘", 2, None),
    ("mas fotos de kiara",   photos('Kiara',2), 3, None),
    ("y la ultima iris tenes otra", [photos('Iris',1)[0] + "\ncielo"], 4, None),
    ("mmm\ny haces precio por 2 chicas", "eso depende guapo, dime cual te gusta y hablamos", 5, None),
    ("no se estoy viendo\ny si voy media hora pero con valentina y kiara", "primero dime cual chica te gusta y luego vemos 😘", 6, None),
    ("bueno valentina. donde estais?", "en el centro cielo, zona discreta y tranquila. confirmame media hora con Valentina y te paso el maps 😘", 7, "Valentina"),
    ("ya te escribo",        "dale 😘", 8, None),
    # dead
]

# ── 9. MAREADOR — Regatea, pregunta cosas raras, nunca decide ──────────
P9 = photos('Iris',1)
C09 = [
    ("Buenas",               "holaaa guapo 😘", 0, None),
    ("q tal, una consulta",  "dime cielo", 1, None),
    ("tienes alguna mulata o brasileña?", "si! tengo a Iris q es brasileña de verdad, un cuerpazo 😍", 2, None),
    ("a ver fotos de iris",  P9, 3, "Iris"),
    ("mm esta buena. precios?", "Iris 50€ media hora, 100€ la hora. si quieres mas barato tengo rapidito 10 min por 40€ 😘", 4, "Iris"),
    ("un poco caro, 30€ media hora?", "no puedo bajar mas amor, son los precios q tengo. 50€ la media hora 😘", 5, "Iris"),
    ("venga 40 y voy ahora", "precio fijo cari, por eso la calidad es buena 😏", 6, "Iris"),
    ("y si vengo con un amigo, nos haces precio?", "cada uno con la suya si, 50€ cada uno la media hora. pero los dos con una chica no 😘", 7, "Iris"),
    ("y teneis sitio limpio?", "si cielo, todo impecable y discreto. en el centro, zona tranquila 😘", 8, "Iris"),
    ("ok te escribo luego a ver", "dale cuando quieras guapo 😘", 9, "Iris"),
]

# ── 10. PREGUNTA RARA — "Tenes chicas trans?" → muerta ─────────────────
C10 = [
    ("hola",                 "holaaa papi 😘", 0, None),
    ("tenes chicas trans?",  "no cielo solo chicas 😅 pero tengo unas bellezas: Valentina, Kiara, Sandra e Iris 😍", 1, None),
    ("ah\nno solo busco trans\ngracias igual", "ok guapo suerte! si cambias de idea ya sabes 😘", 2, None),
]

# ── 11. MUERTA — Hola, info, silencio ─────────────────────────────────
C11 = [
    ("hola",                 "holaaa guapo 😘 q tal? tienes ganas de un ratito rico?", 0, None),
    ("info",                 "claro bb! 40€ rapidito, 50€ media hora, 100€ la hora completa. tengo a Valentina, Kiara, Sandra e Iris disponibles 😍 dime cual te gusta", 1, None),
    # dead
]

# ── 12. INDETERMINADO — "Hoy no puedo, mañana" ──────────────────────────
P12 = photos('Valentina',1)
C12 = [
    ("estan disponibles",    "si amor! Valentina, Kiara, Sandra e Iris 😘 tienes ganas?", 0, None),
    ("precios?",             "40€ rapidito, 50€ media hora, 100€ la horita completa cielo", 1, None),
    ("valentina esta?",      ["si bb Valentina esta libre ahora mismo 😍", P12[0]], 2, "Valentina"),
    ("q linda\nmira hoy no puedo ando complicado\nmañana a la tarde la tenes?",
     "si cielo Valentina trabaja mañana tambien! te espero entonces? 😘", 3, "Valentina"),
    ("si mañana te confirmo bien", "dale guapo hablamos mañana 😘", 4, "Valentina"),
    ("👍",                   None, 5, "Valentina"),
]


# ═══════════════════════════════════════════════════════════════════════════
#  GENERATE
# ═══════════════════════════════════════════════════════════════════════════

all_m = []; all_o = []; all_l = []

def add(tmpls, outc, tags=None, cf=0.80, hok=False, arr=False, line=None, girl=None):
    ago = random.uniform(0, 60)
    for t in tmpls:
        ms, ot, ld = build(t, outc, tags, cf, hok, arr, ago, line, girl)
        all_m.extend(ms); all_o.append(ot)
        if ld: all_l.append(ld)
        ago += random.uniform(4, 16)

# First 2 on Principal — top of sidebar, have typing indicator
add([C01], 'lead_confirmado', [], cf=0.91, hok=True, arr=True, line='Principal', girl='Valentina')
add([C02], 'lead_confirmado', [], cf=0.93, hok=True, arr=True, line='Principal', girl='Kiara')

add([C03], 'lead_probable', [], cf=0.78, line='Principal', girl='Sandra')
add([C04], 'lead_probable', [], cf=0.82, line='Secundaria', girl='Iris')
add([C05], 'lead_probable', [], cf=0.74, line='Secundaria', girl='Valentina')

add([C06], 'lead_ghosted', [], cf=0.65, line='Principal', girl='Kiara')
add([C07], 'lead_ghosted', [], cf=0.60, line='Secundaria', girl='Sandra')

add([C08], 'mareador', ['pide_mapa_sin_chica','no_quiere_pagar'], cf=0.87, line='Secundaria')
add([C09], 'mareador', ['no_quiere_pagar'], cf=0.83, line='Principal', girl='Iris')

add([C10], 'muerta', [], cf=0.80, line='Secundaria')
add([C11], 'muerta', [], cf=0.85, line='Principal')
add([C12], 'indeterminado', [], cf=0.45, line='Secundaria', girl='Valentina')

# ── Sort ─────────────────────────────────────────────────────────────────
by_t = defaultdict(list)
for m in all_m: by_t[m['thread_id']].append(m)
all_s = []
for tid, msgs in by_t.items():
    msgs.sort(key=lambda x: x['ts']); all_s.extend(msgs)
all_o.sort(key=lambda x: x['classified_at'], reverse=True)
all_l.sort(key=lambda x: x['ts'], reverse=True)

# ── Chat features ────────────────────────────────────────────────────────
PR = LINE_LAST9.get('Principal','')
typing_tids = sorted([t for t in by_t if t.startswith(PR+'_')])[:2]
for t in typing_tids: print(f"  ⏳ typing: {t}")

last_ts_map = {}
for m in all_s: last_ts_map[m['thread_id']] = m['ts']
read_status = {}
unread_tids = list(typing_tids)
for t in sorted(last_ts_map.keys()):
    if t not in unread_tids and len(unread_tids) < 4: unread_tids.append(t)
for t in unread_tids:
    read_status[t] = (datetime.fromisoformat(last_ts_map[t]) - timedelta(days=2)).isoformat()

paused_tids = []
for t in sorted(last_ts_map.keys()):
    if t not in typing_tids and len(paused_tids) < 2:
        mc = len(by_t.get(t,[]))
        if mc >= 3: paused_tids.append(t)

# ── Write ────────────────────────────────────────────────────────────────
def wnd(path, recs):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'w', encoding='utf-8') as f:
        for r in recs: f.write(json.dumps(r, ensure_ascii=False)+'\n')
    print(f"  ✓ {os.path.basename(path)}: {len(recs)} lines")

wnd(os.path.join(DATA, 'session_memory.ndjson'), all_s)
wnd(os.path.join(DATA, 'conversation_outcomes.ndjson'), all_o)
wnd(os.path.join(DATA, 'leads.ndjson'), all_l)

with open(os.path.join(DATA, 'read_status.json'), 'w') as f:
    json.dump(read_status, f, ensure_ascii=False)
print(f"  ✓ read_status.json: {len(read_status)} unread")

with open(os.path.join(DATA, 'paused_threads.ndjson'), 'w') as f:
    for t in paused_tids:
        f.write(json.dumps({"thread_id":t,"paused_at":datetime.now(timezone.utc).isoformat()}, ensure_ascii=False)+'\n')
print(f"  ✓ paused_threads.ndjson: {len(paused_tids)} paused")

# ── Playbook ─────────────────────────────────────────────────────────────
total = len(all_o)
lt = sum(1 for o in all_o if o['outcome'] in ('lead_probable','lead_confirmado'))
lok = sum(1 for o in all_o if o['outcome']=='lead_confirmado')
playbook = f"""# 🧠 Playbook de CasaWasap — Demo

> Generado automaticamente a partir de {total} conversaciones clasificadas.
> Actualizado: {BASE.strftime('%d/%m/%Y %H:%M')} UTC

## 📊 Resumen
- Conversaciones analizadas: **{total}**
- Leads totales: **{lt}** ({int(lt/total*100)}%)
- Leads confirmados: **{lok}** ({int(lok/lt*100) if lt else 0}% de leads)
- Ghosteos: **{sum(1 for o in all_o if o['outcome']=='lead_ghosted')}**
- Mareadores: **{sum(1 for o in all_o if o['outcome']=='mareador')}**

## 🎯 Patrones
- ETA concreto → 78% llegan
- Elige chica rapido → intencion real
- Monosiabo ("ok") tras maps → ghosteo inminente
- Pide fotos de 3+ sin decidir → mareador

## 🚫 Anti-mareador
- Limite 3 fotos sin eleccion
- No negociar: "precio fijo cari"
- No dar direccion sin chica elegida

---
*Playbook simulado. Con tus conversaciones reales se genera solo cada dia.*
"""

with open(os.path.join(DATA, 'playbook.md'), 'w') as f:
    f.write(playbook)
print(f"  ✓ playbook.md: {len(playbook)} chars")

# ── Clear stale ──────────────────────────────────────────────────────────
for stub in ['followups_log.ndjson', 'reminders_pending.ndjson']:
    with open(os.path.join(DATA, stub), 'w') as f: f.write('')

# ── Summary ──────────────────────────────────────────────────────────────
print(f"\n✅ {total} conversations, {len(all_s)} msgs, {len(all_l)} leads")
print(f"   ⏳typing={len(typing_tids)} 🔴unread={len(unread_tids)} ⏸paused={len(paused_tids)}")
