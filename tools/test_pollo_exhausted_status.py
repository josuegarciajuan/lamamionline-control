#!/usr/bin/env python3
"""Regression tests for Pollo exhausted-state transitions."""

import importlib.util
import json
import pathlib
import tempfile
import unittest


WORKER_PATH = pathlib.Path(__file__).with_name("pollo_image_worker.py")


def load_worker():
    spec = importlib.util.spec_from_file_location("pollo_image_worker_test", WORKER_PATH)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


class ExhaustedStatusTest(unittest.TestCase):
    def setUp(self):
        self.worker = load_worker()
        self.temp_dir = tempfile.TemporaryDirectory()
        self.status_file = pathlib.Path(self.temp_dir.name) / "pollo_accounts_status.json"

    def tearDown(self):
        self.temp_dir.cleanup()

    def write_status(self, data):
        self.status_file.write_text(json.dumps(data), encoding="utf-8")

    def read_status(self):
        return json.loads(self.status_file.read_text(encoding="utf-8"))

    def test_recovered_account_exhaustion_clears_previous_notification_marker(self):
        self.write_status({
            "Cuenta 1": {
                "credits_exhausted": False,
                "exhaustion_notified_at": "2026-08-15 10:00:00",
                "recovered_at": "2026-08-15 11:00:00",
            },
        })

        self.worker._write_exhausted_status(str(self.status_file), "Cuenta 1")

        account = self.read_status()["Cuenta 1"]
        self.assertTrue(account["credits_exhausted"])
        self.assertNotIn("exhaustion_notified_at", account)

    def test_repeated_exhaustion_keeps_notification_marker(self):
        self.write_status({
            "Cuenta 1": {
                "credits_exhausted": True,
                "exhaustion_notified_at": "2026-08-15 10:00:00",
            },
        })

        self.worker._write_exhausted_status(str(self.status_file), "Cuenta 1")

        account = self.read_status()["Cuenta 1"]
        self.assertEqual("2026-08-15 10:00:00", account["exhaustion_notified_at"])


if __name__ == "__main__":
    unittest.main()
