import unittest
import helioviewer.jp2 as jp2
import requests
import tempfile
import json
import os
import mimetypes

class TestVsoDownload(unittest.TestCase):

    def test_getImageGroup(self):

        print(os.environ)
        URL = "http://localhost:%s/?action=getSciDataScript&imageScale=4.84088176&sourceIds=[13,10]&startDate=2021-06-01T00:01:00Z&endDate=2021-06-01T00:02:00Z&lang=sunpy&provider=vso" % os.environ['API_PORT'];

        response = requests.get(URL)

        self.assertEqual(200, response.status_code)
        self.assertEqual("OK", response.reason)

        script_with_paths = response.content.replace(b'os.path.expanduser(\'~/\')', bytes('\'/tmp/\'', encoding='utf-8'))

        try: 
            script_for_compile = compile(script_with_paths, '', 'exec', flags=0, dont_inherit=True)
        except SyntaxError:
            self.fail("Test Fail: Could not compile downloaded sunpy vso script , possible error in script")

        locals = {};
        exec(script_for_compile,globals(), locals)

        self.assertGreater(len(locals['data_aia_304']), 0)
        self.assertGreater(len(locals['data_aia_171']), 0)


        for df in locals['data_aia_304']: 
            self.assertTrue(os.path.exists(df))
            self.assertGreater(os.path.getsize(df), 1000000)
            mime = mimetypes.guess_type(df);
            self.assertEqual(mime[0], 'image/fits')

        for dfa in locals['data_aia_171']: 
            self.assertTrue(os.path.exists(dfa))
            self.assertGreater(os.path.getsize(dfa), 1000000)
            mime = mimetypes.guess_type(dfa);
            self.assertEqual(mime[0], 'image/fits')

if __name__ == '__main__':
    unittest.main()
