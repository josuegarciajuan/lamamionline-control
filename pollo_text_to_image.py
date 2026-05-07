#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import json
import time
import urllib.request
import urllib.error
from urllib.parse import urlparse
import os

API_KEY = "pollo_2cyPUx2LN2VayY7wBvLtw2Dkm0ptQwRYNM7xGHkARrQH"

MODEL_BRAND = "google"
MODEL_ALIAS = "nano-banana"

CREATE_URL = "https://pollo.ai/api/platform/generation/{}/{}".format(MODEL_BRAND, MODEL_ALIAS)

PROMPT = "Retrato editorial de moda de una mujer adulta atractiva y elegante, pose segura, estilismo glamour, iluminación cinematográfica suave, vestido sofisticado, encuadre fotográfico profesional, alta calidad, no explicit, no nudity"
OUTPUT_FILE = "pollo_image_result.jpg"

payload = {
    "input": {
        "prompt": PROMPT
    }
}

headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "x-api-key": API_KEY
}


def http_post_json(url, data, headers):
    req = urllib.request.Request(
        url,
        data=json.dumps(data).encode("utf-8"),
        headers=headers,
        method="POST"
    )
    with urllib.request.urlopen(req, timeout=60) as resp:
        body = resp.read().decode("utf-8")
        return json.loads(body) if body else {}


def http_get_json(url, headers):
    req = urllib.request.Request(url, headers=headers, method="GET")
    with urllib.request.urlopen(req, timeout=60) as resp:
        body = resp.read().decode("utf-8")
        return json.loads(body) if body else {}


def detect_filename_from_url(url, fallback_name):
    path = urlparse(url).path
    name = os.path.basename(path)
    if not name:
        return fallback_name
    return name


def download_file(url, filename=None):
    if not filename:
        filename = detect_filename_from_url(url, OUTPUT_FILE)

    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
    with urllib.request.urlopen(req, timeout=300) as resp:
        data = resp.read()

    with open(filename, "wb") as f:
        f.write(data)

    return filename


def main():
    try:
        print("Creando tarea de imagen en Pollo AI...")
        print("URL:", CREATE_URL)

        create_resp = http_post_json(CREATE_URL, payload, headers)
        print("Respuesta creación:")
        print(json.dumps(create_resp, indent=2, ensure_ascii=False))

        task_id = create_resp.get("taskId")
        if not task_id:
            print("No se recibió taskId.")
            return

        status_url = "https://pollo.ai/api/platform/generation/{}/status".format(task_id)
        print("Task ID: {}".format(task_id))

        for intento in range(60):
            print("Consultando estado... intento {}/60".format(intento + 1))
            status_resp = http_get_json(status_url, headers)
            print(json.dumps(status_resp, indent=2, ensure_ascii=False))

            generations = status_resp.get("generations", [])
            if generations:
                gen = generations[0]
                estado = gen.get("status")
                media_url = gen.get("url")
                fail_msg = gen.get("failMsg")
                media_type = gen.get("mediaType")

                print("Estado: {} | mediaType: {}".format(estado, media_type))

                if estado in ("succeed", "success", "completed") and media_url:
                    print("Imagen generada. Descargando...")
                    saved_as = download_file(media_url, OUTPUT_FILE)
                    print("Guardada como: {}".format(saved_as))
                    return

                if estado in ("failed", "error", "canceled"):
                    print("La generación falló: {}".format(fail_msg))
                    return

            time.sleep(3)

        print("Timeout esperando a que termine la generación.")

    except urllib.error.HTTPError as e:
        body = e.read().decode("utf-8", errors="replace")
        print("HTTPError: {}".format(e.code))
        print(body)
    except Exception as e:
        print("Error: {}".format(str(e)))


if __name__ == "__main__":
    main()