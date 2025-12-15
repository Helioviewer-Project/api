from unittest.mock import patch
import responses
import unittest
import requests
import os

class TestVsoDownload(unittest.TestCase):

    @patch("sunpy.net.Fido.fetch")
    @responses.activate
    def test_getImageGroup(self, fido_fetch_response):
        # Get API host from environment variable, default to localhost
        host = os.environ.get('PYTEST_API_HOST', 'localhost')
        # Allow requests to the configured host to go through.
        responses.add_passthru(f"http://{host}")
        URL = f"http://{host}/?action=getSciDataScript&imageScale=4.84088176&sourceIds=[13,10]&startDate=2021-06-01T00:01:00Z&endDate=2021-06-01T00:01:15Z&lang=sunpy&provider=vso"

        response = requests.get(URL)

        self.assertEqual(200, response.status_code)
        self.assertEqual("OK", response.reason)

        script_with_paths = response.content.replace(b'os.path.expanduser(\'~/\')', bytes('\'/tmp/\'', encoding='utf-8'))

        try:
            script_for_compile = compile(script_with_paths, '', 'exec', flags=0, dont_inherit=True)
        except SyntaxError:
            self.fail("Test Fail: Could not compile downloaded sunpy vso script , possible error in script")

        test_dir = os.path.dirname(os.path.abspath(__file__))
        request_path = os.path.join(test_dir, 'mocked_requests', 'vso_sunpy_download.yaml')
        responses._add_from_file(file_path=request_path)

        fido_fetch_response.return_value = ['theoretical_file.fits']
        locals = {};
        exec(script_for_compile,globals(), locals)

        self.assertEqual(len(locals['data_aia_304']), 1)
        self.assertEqual(len(locals['data_aia_171']), 1)

if __name__ == '__main__':
    unittest.main()
