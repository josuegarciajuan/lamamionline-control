<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Acuerdo de uso de habitación</title>
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 0;
      background: #f5f6f8;
      font-family: Arial, Helvetica, sans-serif;
      color: #222;
      line-height: 1.55;
    }

    .page {
      width: 900px;
      max-width: 100%;
      margin: 24px auto;
      background: #fff;
      padding: 34px 38px;
      border-radius: 14px;
      box-shadow: 0 8px 26px rgba(0,0,0,0.08);
    }

    .header {
      border-bottom: 3px solid #111;
      padding-bottom: 14px;
      margin-bottom: 24px;
    }

    .title {
      margin: 0;
      font-size: 30px;
      line-height: 1.15;
      font-weight: 800;
      color: #111;
    }

    .subtitle {
      margin-top: 8px;
      font-size: 15px;
      color: #555;
    }

    .summary-box {
      background: #f1f7ff;
      border: 1px solid #cfe2ff;
      border-radius: 12px;
      padding: 18px 18px 14px;
      margin-bottom: 28px;
    }

    .summary-title {
      margin: 0 0 10px 0;
      font-size: 19px;
      font-weight: 800;
      color: #0f3d7a;
    }

    .summary-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 10px 18px;
    }

    .summary-item {
      background: #fff;
      border-radius: 10px;
      padding: 12px;
      border: 1px solid #e4eefc;
    }

    .summary-item strong {
      display: block;
      margin-bottom: 4px;
      font-size: 14px;
      color: #1f2f46;
    }

    .section {
      margin-bottom: 26px;
    }

    .section h2 {
      margin: 0 0 12px;
      font-size: 21px;
      font-weight: 800;
      color: #111;
      border-left: 5px solid #111;
      padding-left: 12px;
    }

    .section p {
      margin: 0 0 12px;
      font-size: 15px;
    }

    .clause {
      margin-bottom: 14px;
      padding: 12px 14px;
      background: #fafafa;
      border: 1px solid #ececec;
      border-radius: 10px;
    }

    .clause strong {
      color: #111;
    }

    .field {
      font-weight: 700;
      color: #0f3d7a;
    }

    .two-cols {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 18px;
    }

    .data-card {
      border: 1px solid #e7e7e7;
      border-radius: 12px;
      padding: 14px;
      background: #fff;
    }

    .data-card h3 {
      margin: 0 0 10px 0;
      font-size: 17px;
      color: #111;
    }

    .data-line {
      margin: 6px 0;
      font-size: 15px;
    }

    .note {
      font-size: 14px;
      color: #555;
      background: #fff8e8;
      border: 1px solid #f3dfaa;
      border-radius: 10px;
      padding: 12px 14px;
      margin-top: 10px;
    }

    .signature-area {
      margin-top: 20px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 22px;
    }

    .sign-box {
      border: 2px dashed #b9b9b9;
      border-radius: 12px;
      min-height: 170px;
      padding: 14px;
      background: #fcfcfc;
    }

    .sign-box h4 {
      margin: 0 0 12px 0;
      font-size: 16px;
    }

    .sign-placeholder {
      height: 70px;
      border: 1px dashed #d1d1d1;
      border-radius: 8px;
      background: #fff;
      margin: 10px 0 14px;
    }

    .meta-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px 16px;
      margin-top: 10px;
      font-size: 14px;
    }

    .annex {
      margin-top: 32px;
      padding-top: 24px;
      border-top: 3px solid #111;
    }

    ul.clean {
      margin: 8px 0 0 18px;
      padding: 0;
    }

    ul.clean li {
      margin-bottom: 8px;
    }

    .footer {
      margin-top: 30px;
      font-size: 12px;
      color: #777;
      text-align: center;
    }

    .small {
      font-size: 13px;
      color: #666;
    }

    @media print {
      body {
        background: #fff;
      }
      .page {
        margin: 0;
        width: 100%;
        box-shadow: none;
        border-radius: 0;
      }
    }

    @media (max-width: 700px) {
      .page {
        padding: 20px 16px;
      }

      .summary-grid,
      .two-cols,
      .signature-area,
      .meta-grid {
        grid-template-columns: 1fr;
      }

      .title {
        font-size: 25px;
      }
    }
  </style>
</head>
<body>
  <div class="page">
    <div class="header">
      <h1 class="title">Acuerdo de uso de habitación</h1>
      <div class="subtitle">
        Documento de uso interno entre las partes para dejar claras las condiciones de ocupación, pago y convivencia.
      </div>
    </div>

    <div class="summary-box">
      <h2 class="summary-title">Resumen rápido</h2>
      <div class="summary-grid">
        <div class="summary-item">
          <strong>Titular del inmueble</strong>
          [nombre_arrendadora]
        </div>
        <div class="summary-item">
          <strong>Persona ocupante</strong>
          [nombre_ocupante]
        </div>
        <div class="summary-item">
          <strong>Habitación / plaza</strong>
          [habitacion_o_plaza]
        </div>
        <div class="summary-item">
          <strong>Dirección</strong>
          [direccion_inmueble]
        </div>
        <div class="summary-item">
          <strong>Importe</strong>
          [importe_semanal_o_periodico]
        </div>
        <div class="summary-item">
          <strong>Inicio</strong>
          [fecha_inicio]
        </div>
      </div>
      <div class="note">
        Este resumen es solo una ayuda visual. Lo que vale es el contenido completo del documento firmado.
      </div>
    </div>

    <div class="section">
      <h2>1. Partes</h2>
      <div class="two-cols">
        <div class="data-card">
          <h3>Parte arrendadora / cedente</h3>
          <div class="data-line"><strong>Nombre:</strong> <span class="field">[nombre_arrendadora]</span></div>
          <div class="data-line"><strong>DNI/NIE:</strong> <span class="field">[dni_arrendadora]</span></div>
          <div class="data-line"><strong>Teléfono:</strong> <span class="field">[telefono_arrendadora]</span></div>
          <div class="data-line"><strong>Domicilio de contacto:</strong> <span class="field">[domicilio_arrendadora]</span></div>
        </div>

        <div class="data-card">
          <h3>Parte ocupante</h3>
          <div class="data-line"><strong>Nombre:</strong> <span class="field">[nombre_ocupante]</span></div>
          <div class="data-line"><strong>DNI/NIE/Pasaporte:</strong> <span class="field">[documento_ocupante]</span></div>
          <div class="data-line"><strong>Teléfono:</strong> <span class="field">[telefono_ocupante]</span></div>
          <div class="data-line"><strong>Nacionalidad:</strong> <span class="field">[nacionalidad_ocupante]</span></div>
        </div>
      </div>
    </div>

    <div class="section">
      <h2>2. Objeto del acuerdo</h2>

      <div class="clause">
        La parte arrendadora / cedente autoriza a la parte ocupante al uso de la habitación o plaza identificada como
        <strong class="field">[habitacion_o_plaza]</strong>,
        situada en el inmueble ubicado en
        <strong class="field">[direccion_inmueble]</strong>.
      </div>

      <div class="clause">
        Este acuerdo regula únicamente el uso del espacio asignado, así como las condiciones económicas y de convivencia
        aceptadas por ambas partes.
      </div>

      <div class="clause">
        La parte ocupante declara haber visto el espacio, entender las condiciones básicas de uso y aceptar las normas
        recogidas en este documento y en su anexo.
      </div>
    </div>

    <div class="section">
      <h2>3. Duración</h2>

      <div class="clause">
        El uso comenzará el día <strong class="field">[fecha_inicio]</strong>.
      </div>

      <div class="clause">
        La duración inicial pactada será de <strong class="field">[duracion_inicial]</strong>, con posibilidad de continuidad
        por periodos sucesivos si ambas partes están conformes y se mantiene el pago en tiempo y forma.
      </div>

      <div class="clause">
        Cualquiera de las partes podrá dar por finalizado este acuerdo conforme a lo indicado en la cláusula de finalización.
      </div>
    </div>

    <div class="section">
      <h2>4. Precio y forma de pago</h2>

      <div class="clause">
        La parte ocupante abonará a la parte arrendadora / cedente la cantidad de
        <strong class="field">[importe_semanal_o_periodico]</strong>
        por el uso del espacio descrito.
      </div>

      <div class="clause">
        El pago se realizará con periodicidad <strong class="field">[periodicidad_pago]</strong>,
        mediante <strong class="field">[metodo_pago]</strong>,
        en la fecha o día acordado: <strong class="field">[dia_pago]</strong>.
      </div>

      <div class="clause">
        En caso de existir fianza o depósito, su importe será de
        <strong class="field">[importe_fianza]</strong>.
      </div>

      <div class="clause">
        Los retrasos reiterados en el pago podrán ser causa de finalización del acuerdo.
      </div>
    </div>

    <div class="section">
      <h2>5. Qué incluye el uso</h2>

      <div class="clause">
        Salvo pacto distinto, el uso incluye:
        <ul class="clean">
          <li>Uso del espacio asignado: <strong class="field">[habitacion_o_plaza]</strong></li>
          <li>Acceso a zonas comunes permitidas: <strong class="field">[zonas_comunes_permitidas]</strong></li>
          <li>Suministros o servicios incluidos, si los hubiera: <strong class="field">[suministros_incluidos]</strong></li>
        </ul>
      </div>

      <div class="clause">
        No se entenderá incluido nada que no figure expresamente en este documento o en un anexo firmado por ambas partes.
      </div>
    </div>

    <div class="section">
      <h2>6. Normas básicas de convivencia</h2>

      <div class="clause">
        La parte ocupante se compromete a usar el espacio con respeto, cuidado y sentido común, evitando molestias,
        suciedad, daños o conflictos con otras personas del inmueble.
      </div>

      <div class="clause">
        Se deberán respetar especialmente las siguientes reglas:
        <ul class="clean">
          <li>Horarios básicos de entrada, salida o silencio: <strong class="field">[horarios_basicos]</strong></li>
          <li>Condiciones de visitas: <strong class="field">[normas_visitas]</strong></li>
          <li>Condiciones de limpieza y orden: <strong class="field">[normas_limpieza]</strong></li>
          <li>Uso de cocina, baño y zonas comunes: <strong class="field">[uso_zonas_comunes]</strong></li>
          <li>Prohibiciones especiales, si las hubiera: <strong class="field">[prohibiciones_especiales]</strong></li>
        </ul>
      </div>
    </div>

    <div class="section">
      <h2>7. Conservación del espacio</h2>

      <div class="clause">
        La parte ocupante recibe el espacio en estado adecuado para su uso y se compromete a devolverlo en un estado similar,
        salvo el desgaste normal derivado de un uso correcto.
      </div>

      <div class="clause">
        En caso de daños causados por mal uso, negligencia o incumplimiento de las normas, la parte ocupante responderá de los mismos.
      </div>
    </div>

    <div class="section">
      <h2>8. Finalización del acuerdo</h2>

      <div class="clause">
        Este acuerdo podrá finalizar por cualquiera de estas causas:
        <ul class="clean">
          <li>Mutuo acuerdo entre las partes.</li>
          <li>Falta de pago o retrasos reiterados.</li>
          <li>Incumplimiento grave de las normas de convivencia.</li>
          <li>Daños intencionados o uso inadecuado del espacio.</li>
          <li>Preaviso de cualquiera de las partes con una antelación de <strong class="field">[dias_preaviso]</strong>.</li>
        </ul>
      </div>
    </div>

    <div class="section">
      <h2>9. Comunicaciones</h2>

      <div class="clause">
        Las partes acuerdan que cualquier aviso, incidencia o comunicación relacionada con este acuerdo podrá realizarse por
        teléfono, mensaje o por escrito, usando como datos de contacto los indicados en este documento.
      </div>
    </div>

    <div class="section">
      <h2>10. Protección de datos y firma</h2>

      <div class="clause">
        Los datos personales facilitados por ambas partes se utilizarán únicamente para identificar a las partes,
        gestionar este acuerdo, registrar su firma y conservar evidencia de aceptación del contenido firmado.
      </div>

      <div class="clause">
        La parte ocupante declara haber leído este documento completo, haber podido resolver sus dudas y firmarlo de manera libre y voluntaria.
      </div>

      <div class="signature-area">
        <div class="sign-box">
          <h4>Firma parte arrendadora / cedente</h4>
          <div><strong>Nombre:</strong> [nombre_arrendadora]</div>
          <div><strong>Documento:</strong> [dni_arrendadora]</div>
          <div class="sign-placeholder"></div>
          <div><strong>Fecha y hora:</strong> [fecha_hora_firma_arrendadora]</div>
        </div>

        <div class="sign-box">
          <h4>Firma parte ocupante</h4>
          <div><strong>Nombre:</strong> [nombre_ocupante]</div>
          <div><strong>Documento:</strong> [documento_ocupante]</div>
          <div class="sign-placeholder"></div>
          <div><strong>Fecha y hora:</strong> [fecha_hora_firma_ocupante]</div>
        </div>
      </div>

      <div class="note">
        Datos técnicos de firma a conservar por el sistema: IP <strong>[ip_firma]</strong>,
        dispositivo <strong>[dispositivo_firma]</strong>,
        navegador <strong>[navegador_firma]</strong>,
        identificador de operación <strong>[id_firma]</strong>,
        huella/hash del documento <strong>[hash_documento]</strong>.
      </div>
    </div>

    <div class="annex">
      <div class="section">
        <h2>Anexo I. Normas de convivencia</h2>

        <div class="clause">
          <ul class="clean">
            <li>La habitación o plaza asignada es: <strong class="field">[habitacion_o_plaza]</strong>.</li>
            <li>Las zonas comunes permitidas son: <strong class="field">[zonas_comunes_permitidas]</strong>.</li>
            <li>El horario orientativo de descanso o silencio será: <strong class="field">[horario_silencio]</strong>.</li>
            <li>Las visitas estarán permitidas o limitadas de la siguiente forma: <strong class="field">[normas_visitas]</strong>.</li>
            <li>La limpieza básica del espacio personal corresponde a la ocupante.</li>
            <li>La limpieza de zonas comunes se realizará según: <strong class="field">[sistema_limpieza]</strong>.</li>
            <li>No se permite causar molestias graves, peleas, daños, suciedad excesiva ni situaciones que alteren la convivencia.</li>
            <li>Cualquier incidencia relevante deberá comunicarse cuanto antes al teléfono: <strong class="field">[telefono_contacto_incidencias]</strong>.</li>
          </ul>
        </div>

        <p class="small">
          Este anexo forma parte del acuerdo principal y se considera aceptado con la firma del documento.
        </p>
      </div>
    </div>

    <div class="footer">
      Plantilla base para integrar en CRM · Versión 1
    </div>
  </div>
</body>
</html>