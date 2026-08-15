#!/usr/bin/env python3
"""Regression tests for Pollo exhausted-state transitions."""

import importlib.util
import fcntl
import json
import os
import pathlib
import subprocess
import sys
import tempfile
import time
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
                "exhausted_at": "2026-08-15 09:00:00 UTC",
                "exhaustion_notified_at": "2026-08-15 10:00:00",
            },
        })

        self.worker._write_exhausted_status(str(self.status_file), "Cuenta 1")

        account = self.read_status()["Cuenta 1"]
        self.assertEqual("2026-08-15 10:00:00", account["exhaustion_notified_at"])
        self.assertEqual("2026-08-15 09:00:00 UTC", account["exhausted_at"])

    def test_shared_lock_mode_and_status_mode_are_preserved(self):
        self.write_status({"Cuenta 1": {"credits_exhausted": False}})
        self.status_file.chmod(0o640)
        owner_before = self.status_file.stat().st_uid
        group_before = self.status_file.stat().st_gid

        self.worker._write_exhausted_status(str(self.status_file), "Cuenta 1")

        self.assertEqual(0o640, self.status_file.stat().st_mode & 0o777)
        self.assertEqual(owner_before, self.status_file.stat().st_uid)
        self.assertEqual(group_before, self.status_file.stat().st_gid)
        lock_path = pathlib.Path(str(self.status_file) + ".lock")
        self.assertEqual(0o666, lock_path.stat().st_mode & 0o777)

        self.status_file.chmod(0o644)
        self.worker._write_exhausted_status(str(self.status_file), "Cuenta 1")
        self.assertTrue(self.status_file.stat().st_mode & 0o004)

    def test_malformed_existing_status_is_not_replaced(self):
        self.status_file.write_text("{malformed", encoding="utf-8")

        self.worker._write_exhausted_status(str(self.status_file), "Cuenta 1")

        self.assertEqual("{malformed", self.status_file.read_text(encoding="utf-8"))

    def test_python_writer_waits_for_shared_lock_and_preserves_concurrent_state(self):
        self.write_status({"PHP": {"credits_exhausted": False, "custom": "keep"}})
        lock_path = pathlib.Path(str(self.status_file) + ".lock")
        lock_path.touch()
        code = (
            "import importlib.util,pathlib,sys;"
            "s=importlib.util.spec_from_file_location('w',sys.argv[1]);"
            "m=importlib.util.module_from_spec(s);s.loader.exec_module(m);"
            "pathlib.Path(sys.argv[3]).write_text('ready');"
            "m._write_exhausted_status(sys.argv[2],'Python')"
        )
        ready_path = pathlib.Path(self.temp_dir.name) / "python-ready"
        with lock_path.open("a+") as lock_handle:
            fcntl.flock(lock_handle.fileno(), fcntl.LOCK_EX)
            process = subprocess.Popen([sys.executable, "-c", code, str(WORKER_PATH), str(self.status_file), str(ready_path)])
            for _ in range(100):
                if ready_path.exists():
                    break
                time.sleep(0.02)
            self.assertTrue(ready_path.exists(), "Python child did not become ready")
            time.sleep(0.1)
            self.assertIsNone(process.poll(), "Python writer must wait for the compatible lock")
            self.write_status({"PHP": {"credits_exhausted": True, "custom": "keep"}})
            fcntl.flock(lock_handle.fileno(), fcntl.LOCK_UN)
        self.assertEqual(0, process.wait(timeout=5))
        status = self.read_status()
        self.assertTrue(status["PHP"]["credits_exhausted"])
        self.assertEqual("keep", status["PHP"]["custom"])
        self.assertTrue(status["Python"]["credits_exhausted"])

    def test_php_writer_uses_same_lock_and_preserves_python_state(self):
        data_dir = pathlib.Path(self.temp_dir.name) / "data"
        data_dir.mkdir()
        php_status = data_dir / "pollo_accounts_status.json"
        php_status.write_text(json.dumps({"Python": {"custom": "before"}}), encoding="utf-8")
        lock_path = pathlib.Path(str(php_status) + ".lock")
        lock_path.touch()
        project_path = WORKER_PATH.parent.parent
        php_code = (
            'define("BASE_PATH", getenv("POLLO_TEST_BASE"));'
            'define("APP_PATH", getenv("POLLO_TEST_APP"));'
            'define("DATA_PATH", BASE_PATH . "/data");'
            'require APP_PATH . "/helpers.php";'
            'require APP_PATH . "/storage.php";'
            'require APP_PATH . "/publicista.php";'
            'publicista_pollo_mark_exhausted("PHP");'
        )
        env = dict(os.environ, POLLO_TEST_BASE=self.temp_dir.name, POLLO_TEST_APP=str(project_path / "app"))
        with lock_path.open("a+") as lock_handle:
            fcntl.flock(lock_handle.fileno(), fcntl.LOCK_EX)
            process = subprocess.Popen(["php", "-r", php_code], env=env)
            time.sleep(0.2)
            self.assertIsNone(process.poll(), "PHP writer must wait for the compatible lock")
            php_status.write_text(json.dumps({"Python": {"custom": "preserved"}}), encoding="utf-8")
            fcntl.flock(lock_handle.fileno(), fcntl.LOCK_UN)
        self.assertEqual(0, process.wait(timeout=5))
        status = json.loads(php_status.read_text(encoding="utf-8"))
        self.assertEqual("preserved", status["Python"]["custom"])
        self.assertTrue(status["PHP"]["credits_exhausted"])


if __name__ == "__main__":
    unittest.main()
