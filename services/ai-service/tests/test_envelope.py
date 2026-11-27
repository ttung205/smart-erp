"""Envelope phản hồi chuẩn: meta.correlation_id và mã lỗi theo docs/contracts/api-spec.md."""
import unittest

from app import envelope


class EnvelopeTest(unittest.TestCase):
    def setUp(self):
        envelope.bind_correlation_id("req_corr_test")

    def test_ok_carries_correlation_id_and_timestamp(self):
        body = envelope.ok({"answer": "x"})
        self.assertTrue(body["success"])
        self.assertEqual(body["meta"]["correlation_id"], "req_corr_test")
        self.assertIsInstance(body["meta"]["timestamp"], int)

    def test_status_is_mapped_to_standard_codes(self):
        expected = {401: "UNAUTHENTICATED", 403: "PERMISSION_DENIED", 404: "RESOURCE_NOT_FOUND",
                    422: "VALIDATION_FAILED", 503: "DEPENDENCY_UNAVAILABLE"}
        for status, code in expected.items():
            self.assertEqual(envelope.fail(status, "m")["error"]["code"], code)

    def test_unknown_status_falls_back_to_internal_error(self):
        self.assertEqual(envelope.fail(500, "m")["error"]["code"], "INTERNAL_SERVER_ERROR")

    def test_missing_correlation_header_generates_new_id(self):
        generated = envelope.bind_correlation_id(None)
        self.assertTrue(generated.startswith("req_corr_"))
        self.assertEqual(envelope.current_correlation_id(), generated)


if __name__ == "__main__":
    unittest.main()
