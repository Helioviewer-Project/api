import unittest
import helioviewer.jp2 as jp2

class TestJp2(unittest.TestCase):
    EMPTY_GROUP = {
        "groupOne": 0,
        "groupTwo": 0,
        "groupThree": 0
    }

    def test_getImageGroup(self):
        # Testing boundary conditions on XRT groups
        # Lower condition, anything below group 37 and greater than 75
        # should have group values set to 0
        result = jp2.getImageGroup(37)
        self.assertEqual(self.EMPTY_GROUP, result)
        result = jp2.getImageGroup(1)
        self.assertEqual(self.EMPTY_GROUP, result)
        result = jp2.getImageGroup(75)
        self.assertEqual(self.EMPTY_GROUP, result)
        result = jp2.getImageGroup(100)
        self.assertEqual(self.EMPTY_GROUP, result)

        # 38 through 43 should all have groupThree as 10008 and increment
        # group 2 from 10002 and up.
        result = jp2.getImageGroup(38)
        self.assertEqual({"groupOne": 10001, "groupTwo": 10002, "groupThree": 10008}, result)
        result = jp2.getImageGroup(39)
        self.assertEqual({"groupOne": 10001, "groupTwo": 10003, "groupThree": 10008}, result)
        result = jp2.getImageGroup(40)
        self.assertEqual({"groupOne": 10001, "groupTwo": 10004, "groupThree": 10008}, result)
        result = jp2.getImageGroup(41)
        self.assertEqual({"groupOne": 10001, "groupTwo": 10005, "groupThree": 10008}, result)
        result = jp2.getImageGroup(42)
        self.assertEqual({"groupOne": 10001, "groupTwo": 10006, "groupThree": 10008}, result)
        result = jp2.getImageGroup(43)
        self.assertEqual({"groupOne": 10001, "groupTwo": 10007, "groupThree": 10008}, result)

        # The remaining groups repeat, so will only test edge conditions
        result = jp2.getImageGroup(44)
        self.assertEqual({"groupOne": 10001, "groupTwo": 10002, "groupThree": 10009}, result)
        result = jp2.getImageGroup(49)
        self.assertEqual({"groupOne": 10001, "groupTwo": 10007, "groupThree": 10009}, result)
        result = jp2.getImageGroup(50)
        self.assertEqual({"groupOne": 10001, "groupTwo": 10002, "groupThree": 10010}, result)
        result = jp2.getImageGroup(55)
        self.assertEqual({"groupOne": 10001, "groupTwo": 10007, "groupThree": 10010}, result)
        result = jp2.getImageGroup(56)
        self.assertEqual({"groupOne": 10001, "groupTwo": 10002, "groupThree": 10011}, result)
        result = jp2.getImageGroup(61)
        self.assertEqual({"groupOne": 10001, "groupTwo": 10007, "groupThree": 10011}, result)
        result = jp2.getImageGroup(62)
        self.assertEqual({"groupOne": 10001, "groupTwo": 10002, "groupThree": 10012}, result)
        result = jp2.getImageGroup(67)
        self.assertEqual({"groupOne": 10001, "groupTwo": 10007, "groupThree": 10012}, result)
        result = jp2.getImageGroup(69)
        self.assertEqual({"groupOne": 10001, "groupTwo": 10002, "groupThree": 10013}, result)
        result = jp2.getImageGroup(74)
        self.assertEqual({"groupOne": 10001, "groupTwo": 10007, "groupThree": 10013}, result)

        # Special case: ID 68 = mispositioned / mispositioned
        result = jp2.getImageGroup(68)
        self.assertEqual({"groupOne": 10001, "groupTwo": 0, "groupThree": 0}, result)


if __name__ == '__main__':
    unittest.main()
