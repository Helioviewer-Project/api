var solarConstants = {};

solarConstants.radiusMeter = 6.955e8;
solarConstants.radiusKiloMeter = solarConstants.radiusMeter / 1000.;
solarConstants.radiusOpenGL = 1.;

function ymd2jd(y, m, d) {
    jd = 367 * y + ~~(-7 * (y + ~~((m + 9) / 12)) / 4) - 3 * ~~((~~(~~((y + (m - 9) / 7)) / 100) + 1) / 4) + ~~((275 * m) / 9) + d + 1721029. - 0.5;
    return jd;
}

function getL0Degree(year, month, day, et) {
    pi = Math.PI;
    radeg = 180. / pi;
    jd = ymd2jd(year, month, day) + et / 24.;
    t = (jd - 2451545.) / 36525.;
    mnl = 280.46645 + 36000.76983 * t + 0.0003032 * t * t;
    mnl = mnl % 360.;
    mna = 357.52910 + 35999.05030 * t - 0.0001559 * t * t - 0.0000048 * t * t * t;
    mna = mna % 360.;

    c = (1.914600 - 0.004817 * t - 0.000014 * t * t) * Math.sin(mna / radeg) + (0.019993 - 0.000101 * t) * Math.sin(2 * mna / radeg) + 0.000290 * Math.sin(3 * mna / radeg);
    true_long = (mnl + c) % 360.;
    omega = 125.04 - 1934.136 * t;
    ob1 = 23.4392991 - 0.01300417 * t - 0.00059 * t * t / 3600. + 0.001813 * t * t * t / 3600.;

    ob1tom = 125.04452 - 1934.136261 * t;
    Lt = 280.4665 + 36000.7698 * t;
    Lpt = 218.3165 + 481267.8813 * t;
    ob1t = ob1 + 9.2 / 3600. * Math.cos(ob1tom / radeg) + 0.57 / 3600. * Math.cos(2 * Lt / radeg) + 0.1 / 3600. * Math.cos(2 * Lpt / radeg) - 0.09 / 3600. * Math.cos(2 * ob1tom / radeg);

    theta = (jd - 2398220.) * 360. / 25.38;
    k = 73.6667 + 1.3958333 * (jd - 2396758.) / 36525.;
    i = 7.25;
    lamda = true_long - 0.005705;
    lamda2 = lamda - 0.00478 * Math.sin(omega / radeg);
    diff = (lamda - k) / radeg;
    x = Math.atan(-Math.cos(lamda2 / radeg) * Math.tan(ob1t / radeg)) * radeg;
    y = Math.atan(-Math.cos(diff) * Math.tan(i / radeg)) * radeg;
    pa = x + y;

    lat0 = Math.asin(Math.sin(diff) * Math.sin(i / radeg)) * radeg;
    y = -Math.sin(diff) * Math.cos(i / radeg);
    x = -Math.cos(diff);
    eta = Math.atan2(y, x) * radeg + 360.;
    long0 = (eta - theta) % 360. + 360.;
    return long0;
}

function getL0Radians(date) {
    var d = date.getUTCDate();
    var m = date.getUTCMonth() + 1;
    var y = date.getUTCFullYear();
    var nosecs = date.getUTCHours() * 60. * 60. + date.getUTCMinutes() * 60. + date.getUTCSeconds();
    return Math.PI / 180. * getL0Degree(y, m, d, (nosecs) / 60. / 60.);
}

// This method is based on the SolarSoft GET_SUN routine
getB0Radians__ = function(year, month, day, et) {
    radeg = 180. / Math.PI;
    jd = ymd2jd(year, month, day) + et / 24.;
    t = (jd - 2415020.) / 36525.;
    mnl = 279.69668 + 36000.76892 * t + 0.0003025 * t * t;
    mnl = mnl % 360;
    if (mnl < 0.) {
        mnl += 360.;
    }

    mna = 358.47583 + 35999.04975 * t - 0.000150 * t * t - 0.0000033 * t * t * t;
    mna = mna % 360;
    if (mna < 0.) {
        mna += 360.;
    }
    c = (1.919460 - 0.004789 * t - 0.000014 * t * t) * Math.sin(mna / radeg) + (0.020094 - 0.000100 * t) * Math.sin(2 * mna / radeg) + 0.000293 * Math.sin(3 * mna / radeg);

    true_long = (mnl + c) % 360;
    if (true_long < 0.) {
        true_long += 360.;
    }
    k = 74.3646 + 1.395833 * t;

    lamda = true_long - 0.00569;

    diff = (lamda - k) / radeg;

    i = 7.25;

    he_lat = Math.asin(Math.sin(diff) * Math.sin(i / radeg));

    return -he_lat;
}

function getB0Radians(date) {
    var d = date.getUTCDate();
    var m = date.getUTCMonth() + 1;
    var y = date.getUTCFullYear();
    var nosecs = date.getUTCHours() * 60. * 60. + date.getUTCMinutes() * 60. + date.getUTCSeconds();
    return getB0Radians__(y, m, d, (nosecs) / 60. / 60.);
}