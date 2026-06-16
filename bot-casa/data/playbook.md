# Playbook del Bot — Aprendizajes acumulados

> Este archivo se genera automáticamente mediante análisis de conversaciones reales.
> Última actualización: 2026-06-16 02:31:35 UTC
> Conversaciones analizadas: 279 | Leads: 5 | Ghosted: 7 | Mareadores: 146 | Human replies: 913
> Motor de análisis: deepseek-v4-pro

---
## Patrones de éxito (LEADS)

**LEAD #1** destaca por cómo maneja un rechazo. El cliente pide salida a domicilio («Quanto cobra pra vir aqui na minha casa?») y el humano responde: «No salidas» + «Es muy tarde» (razón personal suave) + «Sim» (espejeo cultural, usando portugués como el cliente) + «Vente tú amor». Esta secuencia transforma un «no» en una invitación que el cliente acepta. **El patrón**: cuando dices que no a una petición, da una razón tangible (no genérica) e inmediatamente gira hacia una alternativa atractiva, manteniendo el tono de cercanía. El espejeo lingüístico («Sim») generó una complicidad instantánea que el bot no habría replicado.

**LEAD #3** muestra una apertura humana atípica: «Hola amor, estoy disponibilisima» seguido de «Bien amor aquí aburrida». Esta exageración juguetona («disponibilisima») y la confesión de aburrimiento crean una atmósfera de intimidad y urgencia positiva que no está en el playbook del bot. El cliente rompe el hielo con menos resistencia, a pesar de la confusión posterior con las chicas. **El patrón**: una frase de entrada personal y emocionante, distinta a los saludos estandarizados, reduce la fricción inicial y predispone a la compra.

**LEAD #4** enseña dos movimientos ganadores. Uno, flexibilidad microeconómica: el cliente duda porque tiene 95 € y menciona la comisión del cajero, a lo que el humano responde «De 90 por si cobra comisión» (ofrece precio redondeado para absorber el recargo). Dos, la proyección de experiencia lúdica: «Vente nos emborraxamos, tengo cerveza fria». Esta imagen de fiesta despeja la transacción y engancha al cliente. **El patrón**: un pequeño ajuste por una razón concreta (comisión) demuestra empatía práctica; una pincelada de diversión eleva la cita de intercambio a plan apetecible.

## Patrones de mareo detectados

**MAREO #1**: el cliente ve las fotos de Sandra, Iris, Tania y dice «Era una chica rusa, Raluca». El humano admite no tener fotos de ella pero que estaría disponible, y aun así la conversación muere. El punto de no retorno es justo ahí: cuando el cliente rechaza por completo el catálogo mostrado y pide un nombre que no encaja, por mucho que se intente salvar la venta ya hay un desajuste de expectativas. **Señal de mareo**: si tras el envío de fotos el cliente dice «no» a todas y nombra a alguien que no está presente, es prácticamente seguro que no vendrá. El bot podría responder con un cierre amable y no seguir invirtiendo mensajes.

**MAREO #2**: el cliente pregunta «Follar sin goma guapa». La negativa rotunda y sin alternativa atractiva cortó la conversación. Aunque la seguridad es innegociable, el rechazo seco sin redirigir la fantasía (por ejemplo, enfatizando francés natural sin condón, que sí se ofrece) dejó al cliente sin motivo para seguir. **El patrón**: las peticiones de riesgo desde el inicio suelen ser de curiosos no convertibles, pero una negativa envuelta en una oferta excitante podría filtrar a los genuinos. Si tras eso no hay respuesta, es mareo.

**MAREO #3**: el cliente pregunta «He leído q sois dos amigas?». El bot envía fotos de tres chicas sin ligar la respuesta a la fantasía de trío. La conversación acaba sin más. **El patrón**: cuando el cliente lanza un tema fantasioso no vinculado a una chica concreta, y la respuesta es una descarga de fotos genérica, se pierde el hilo. El bot debería engancharse a la fantasía («sí, podemos ser dos para ti si quieres papi, dime cuál te gusta y te cuento cómo lo haríamos») para mantener el interés.

## Señales tempranas de ghosteo

**GHOST #2** expone un error crítico: el bot repite «vale cari» hasta cinco veces seguidas mientras el cliente suelta un bombardeo de ubicaciones inconexas («Estoy en la calle número 1», «Santa barbara», «20 mn si más no»). Esa verborrea desordenada sin que el cliente elija chica ni confirme precio es una alarma de ghosteo. La repetición robótica del bot agrava la situación porque transmite desinterés; el cliente percibe que habla con una máquina y abandona.

**GHOST #3** repite el patrón ya conocido del cambio de chica en la puerta. Pero aquí la novedad es el mensaje repetitivo del cliente: «Toi en la puerta» tres veces, «Pero que numero de la calle...» dos veces. Ese eco de peticiones urgentes mezclado con impaciencia, cuando la otra chica no es la esperada, anticipa el ghosteo casi al 100%. Si el cliente insiste tres veces en “puerta” o “número” sin recibir una respuesta nítida, la confianza se quiebra y se va.

**GHOST #4** comulga con la bandera roja del regateo extremo: «Tengo 25. 8 minutos». Es una cifra que está muy por debajo del mínimo. Aunque el humano mantuvo firmeza y el cliente volvió más tarde con «Hola amor qtal», nunca se concretó. **Señal**: los «tengo X» con importes ridículos suelen ser de personas que prueban suerte, y el ghosteo es la norma, no la excepción. El bot puede etiquetarlos internamente como bajo potencial y no sobreinvertir.

## Estrategias que funcionaron

1. **Rechazo con giro positivo y espejeo lingüístico**  
   LEAD #1: «No salidas, es muy tarde, Sim, Vente tú amor». No solo se ofrece el incall, sino que se hace en el idioma del cliente (portugués). Esto crea un puente cultural. El bot podría detectar palabras en otros idiomas y devolver alguna palabra equivalente.

2. **Flexibilidad de pago por microcomisiones**  
   LEAD #4: «De 90 por si cobra comisión». El cliente menciona un problema real (cajero cobra comisión y se queda corto). Bajando solo 5 € se elimina una barrera que de otra forma habría roto el trato. No es una negociación agresiva, es un gesto de buena voluntad. Funciona.

3. **Invitación lúdica y personal**  
   LEAD #4: «Vente nos emborraxamos, tengo cerveza fria». Esta frase convierte una cita sexual en un plan de copas y risas. Desactiva la presión y suma un aliciente. Cuando el cliente está en la cuerda floja, un anzuelo de ocio compartido puede inclinar la balanza.

4. **Cierre directo con pregunta de tiempo**  
   ESTILO HUMANO #1: «cuanto tiempo quieres quedar amo» y luego «Amor como vas» + «Vienes?😍». Después de varios silencios del cliente, el humano reintenta con una pregunta cerrada y un emoji de ilusión. Ese doble toque reactivó la conversación y el cliente respondió «Si» y luego «Estoy aquí». La persistencia cálida funciona.

## Momentos de inflexión

**LEAD #4**, el momento que derivó en la queja de «gracias por el engaño» fue:  
`Humano: amor pues espera | tardo 10 minutos` (con el cliente ya en la puerta).  
El cliente había llegado convencido de un servicio inmediato. La promesa implícita de «ya voy» se quebró con esa espera. Para redirigirlo, el bot debería haber gestionado expectativas antes: cuando el cliente dijo «Si, voy» pero sin hora exacta, el bot preguntó «en cuantos min llegas?» pero no obtuvo respuesta. En ese silencio, podía haber enviado un mensaje de anticipación: «cuando me digas la hora exacta te confirmo que estoy lista, así llegas y entras directo 😊». Así se evita el shock de la espera.

**MAREO #1**, la inflexión fue el primer «No. Es una ch