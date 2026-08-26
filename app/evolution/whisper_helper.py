#!/usr/bin/env python3
"""whisper_helper.py — Transcribe un audio (wav 16k mono) con faster-whisper local.

Uso:
    python3 whisper_helper.py <ruta.wav> [modelo]

Modelos: tiny, base, small (default), medium, large-v3. Imprime el texto en stdout.
"""
import sys
import warnings
warnings.filterwarnings("ignore")

def main() -> int:
    if len(sys.argv) < 2:
        print("", end="")
        return 1
    audio = sys.argv[1]
    model = sys.argv[2] if len(sys.argv) > 2 else "small"

    try:
        from faster_whisper import WhisperModel
    except Exception:
        print("", end="")
        return 1

    try:
        model_obj = WhisperModel(model, device="cpu", compute_type="int8")
        segments, _info = model_obj.transcribe(audio, language="es")
        text = "".join(seg.text for seg in segments).strip()
        print(text, end="")
        return 0
    except Exception:
        print("", end="")
        return 1

if __name__ == "__main__":
    sys.exit(main())
