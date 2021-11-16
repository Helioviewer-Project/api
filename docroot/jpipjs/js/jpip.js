/*
 * Author Freek Verstringe.
 */
'use strict';
function Pool(size) {
    var instance = this;
    this.taskQueue = [];
    this.workerQueue = [];
    this.poolSize = size;
    this.addWorkerTask = function(workerTask) {
        if (instance.workerQueue.length > 0) {
            var workerThread = instance.workerQueue.shift();
            workerThread.run(workerTask);
        } else {
            instance.taskQueue.push(workerTask);
        }
    };
    this.init = function() {
        var i;
        for (i = 0; i < size; i++) {
            instance.workerQueue.push(new WorkerThread(instance));
        }
    };
    this.freeWorkerThread = function(workerThread) {
        if (instance.taskQueue.length > 0) {
            var workerTask = instance.taskQueue.shift();
            workerThread.run(workerTask);
        } else {
            instance.workerQueue.push(workerThread);
        }
    };
}

function WorkerThread(parentPool) {
    var instance = this;
    this.parentPool = parentPool;
    this.workerTask = {};
    function dummyCallback(event) {
        instance.workerTask.callback(event);
        instance.parentPool.freeWorkerThread(instance);
    }
    this.run = function(workerTask) {
        this.workerTask = workerTask;
        if (this.workerTask.script !== null) {
            var worker = new Worker(workerTask.script);
            worker.addEventListener('message', dummyCallback, false);
            worker.postMessage(workerTask.startMessage, workerTask.startMessageExt);
        }
    };
}
function WorkerTask(script, callback, msg, msgExt) {
    this.script = script;
    this.callback = callback;
    this.startMessage = msg;
    this.startMessageExt = msgExt;
}
var pool = new Pool(4);
pool.init();

function JPIP() {
}
JPIP.prototype = {
    open : function openJPIP(baseurl, imgname, size, numberOfFrames) {
        var r = new XMLHttpRequest();
        r.open('get', baseurl + imgname + '?len=40000000&metareq=[*]!!&type=jpp-stream&cnew=https&tid=0', true);
        r.responseType = 'arraybuffer';
        r.onreadystatechange = function(responseEvent) {
            if (r.readyState === 4) {
                if (r.status === 200) {
                    var jpip_cnew = r.getResponseHeader('JPIP-cnew');
                    var jpip_cnew_array = jpip_cnew.split(',');
                    var jpip_cnew_dict = {};
                    jpip_cnew_dict.baseurl = baseurl;
                    jpip_cnew_dict.image = imgname;
                    for (var i = 0; i < jpip_cnew_array.length; i++) {
                        var jpip_cnew_pair = jpip_cnew_array[i].split('=');
                        if (jpip_cnew_pair.length == 2) {
                            jpip_cnew_dict[jpip_cnew_pair[0]] = jpip_cnew_pair[1];
                        }
                    }
                    var responseList = [];
                    var byteArray = new Uint8Array(r.response);
                    responseList.push(byteArray);
                    JPIP.prototype.download(jpip_cnew_dict, responseList, size, 0, numberOfFrames);
                } else {
                    console.log('Received an abnormal status ' + r.status);
                    console.log('The response with the abnormal status is ' + r.response);
                }
            }
        };
        r.send();
    },
    close : function closeJPIP(conn) {
        var r = new XMLHttpRequest();
        r.timeout = 1;

        r.open('get', conn.baseurl + 'jpip?cclose=' + conn.cid + '&len=0', true);
        r.onload = function(responseEvent) {
            console.log('Closing connection');
        };
        r.send();
        if (JPIP.prototype.onclose !== undefined) {
            JPIP.prototype.onclose();
        }
    },
    download : function downloadJPIP(conn, responseList, size, curr, numberOfFrames) {
        var r = new XMLHttpRequest();
        r.open('get', conn.baseurl + '/jpip?cid=' + conn.cid + '&fsiz=' + size + ',' + size + '&roff=0,0&rsiz=' + size + ',' + size + '&len=40000&context=jpxl<' + curr + '-' + curr + '>', true);
        r.responseType = 'arraybuffer';
        r.onreadystatechange = function(responseEvent) {
            if (r.readyState === 4) {
                if (r.status === 200) {
                    var byteArray = new Uint8Array(r.response);
                    responseList.push(byteArray);
                    if (byteArray.length > 3) {
                        JPIP.prototype.download(conn, responseList, size, curr, numberOfFrames);
                    } else {
                        if (curr < numberOfFrames - 1) {
                            JPIP.prototype.decode(responseList, size, curr);
                            curr = curr + 1;
                            var newresponseList = [ responseList[0] ];
                            JPIP.prototype.download(conn, newresponseList, size, curr, numberOfFrames);
                        } else {
                            //JPIP.prototype.close(conn);
                        }
                    }
                } else {
                    console.log('Received an abnormal status ' + r.status);
                    console.log('The response with the abnormal status is ' + r.response);
                }
            }
        };
        r.send();
    },
    decode : function decode(responseList, size, curr) {
        var precinctList = [];
        var headerList = [];
        var mainHeaderList = [];
        for (var ind = 0; ind < responseList.length; ind++) {
            var offset = 0;
            var vbasClass = 0;
            do {
                var b = readUINT8(responseList[ind][offset]);
                offset++;
                var binidAdditional = (b[1] << 1) + b[2];
                var metadataPieces = [];
                var binid = 0;
                binid += b[7] + (b[6] << 1) + (b[5] << 2) + (b[4] << 3);
                while (b[0] == 1) {
                    b = readUINT8(responseList[ind][offset]);
                    for (var i = 1; i < 8; i++) {
                        binid = (binid << 1) + b[i];
                    }
                    offset++;
                }
                if (binidAdditional !== 0) {
                    var vbasDict = {};
                    vbasDict.binid = binid;
                    vbasDict.ind = ind;
                    vbasDict.binidAdditional = binidAdditional;
                    switch (binidAdditional) {
                        case 1:
                            vbasDict['class'] = vbasClass;
                            offset = this.readVBAS(responseList[ind], offset, vbasDict, 'message_offset');
                            offset = this.readVBAS(responseList[ind], offset, vbasDict, 'message_length');
                            break;
                        case 2:
                            offset = this.readVBAS(responseList[ind], offset, vbasDict, 'class');
                            offset = this.readVBAS(responseList[ind], offset, vbasDict, 'message_offset');
                            offset = this.readVBAS(responseList[ind], offset, vbasDict, 'message_length');
                            break;
                        case 3:
                            offset = this.readVBAS(responseList[ind], offset, vbasDict, 'class');
                            offset = this.readVBAS(responseList[ind], offset, vbasDict, 'CSN');
                            offset = this.readVBAS(responseList[ind], offset, vbasDict, 'message_offset');
                            offset = this.readVBAS(responseList[ind], offset, vbasDict, 'message_length');
                            break;
                    }
                    vbasClass = vbasDict['class'];
                    vbasDict.beginOffsetNoHeader = offset;
                    vbasDict.responseListIndex = ind;
                    switch (vbasDict['class']) {
                        case 0:
                            // Data messages
                            if (!(vbasDict.binid in precinctList)) {
                                precinctList[vbasDict.binid] = [];
                            }
                            precinctList[vbasDict.binid].push(vbasDict);
                            break;
                        case 2:
                            // Tile header
                            headerList.push(vbasDict);
                            break;
                        case 6:
                            // Main header
                            headerList.push(vbasDict);
                            break;
                        case 8:
                            // Metadata
                            headerList.push(vbasDict);
                            break;
                    }
                    offset = offset + vbasDict.message_length;
                    vbasDict.endOffsetNoHeader = offset;
                } else {
                    offset = responseList[ind].length;
                }
            } while (offset < responseList[ind].length);
        }

        var fullheaderdata = this.concatenateHeaderData(headerList, responseList);
        var fulldata = this.concatenateData(precinctList, responseList);
        var alldata = this.concatenateHeaderAndData(fullheaderdata, fulldata);
        var callback = function(e) {
            if (JPIP.prototype.onload !== undefined) {
                JPIP.prototype.onload(e.data);
            }
        };
        var msg;
        var msgExt;
        if (curr === 0) {
            msg = [ 'meta', alldata.buffer, size, curr ];
            msgExt = [ alldata.buffer ];
        } else {
            msg = [ 'data', alldata.buffer, size, curr ];
            msgExt = [ alldata.buffer ];
        }
        var workerTask = new WorkerTask('./js/jpxcombined.js', callback, msg, msgExt);
        pool.addWorkerTask(workerTask);
    },
    concatenateHeaderAndData : function concatenateHeaderAndData(fullheaderdata, fulldata) {
        var alldata = new Uint8Array(fulldata.length + fullheaderdata.length + 4);
        alldata.set(fullheaderdata, 0);
        alldata.set([ 255, 147 ], fullheaderdata.length);
        alldata.set(fulldata, fullheaderdata.length + 2);
        alldata.set([ 255, 217 ], fulldata.length + fullheaderdata.length + 2);
        return alldata;
    },
    getDataLength : function getDataLength(precinctList, responseList) {
        var lenn = 0;
        var helparr, prec, data, realdata;
        for (var i = 0; i < precinctList.length; i++) {
            helparr = precinctList[i];
            for (var j = 0; j < helparr.length; j++) {
                prec = helparr[j];
                data = responseList[prec.responseListIndex];
                realdata = data.subarray(prec.beginOffsetNoHeader, prec.endOffsetNoHeader);
                lenn += realdata.length;
            }
        }
        return lenn;
    },
    concatenateData : function concatenateData(precinctList, responseList) {
        var lenn = JPIP.prototype.getDataLength(precinctList, responseList);
        var helparr, prec, data, realdata;
        var fulldata = new Uint8Array(lenn);
        lenn = 0;
        for (var i = 0; i < precinctList.length; i++) {
            helparr = precinctList[i];
            for (var j = 0; j < helparr.length; j++) {
                prec = helparr[j];
                data = responseList[prec.responseListIndex];
                realdata = data.subarray(prec.beginOffsetNoHeader, prec.endOffsetNoHeader);
                fulldata.set(realdata, lenn);
                lenn += realdata.length;
            }
        }
        return fulldata;
    },
    getHeaderDataLength : function getHeaderDataLength(headerList, responseList) {
        var length = 0;
        var realdata;
        var headerel, data, tbox;
        for (var i = 0; i < headerList.length; i++) {
            headerel = headerList[i];
            data = responseList[headerel.responseListIndex];
            tbox = readUint32(data, headerel.beginOffsetNoHeader + 4);
            if (tbox != 221885891684) {
                realdata = data.subarray(headerel.beginOffsetNoHeader, headerel.endOffsetNoHeader);
            } else {
                var headerType = String.fromCharCode(tbox >> 24 & 255, tbox >> 16 & 255, tbox >> 8 & 255, tbox & 255);
                warn('xUnsupported header type ' + tbox + ' (' + headerType + ')');
                realdata = data.subarray(headerel.beginOffsetNoHeader + 20, headerel.beginOffsetNoHeader + 28);
            }
            length += realdata.length;
        }
        return length;
    },
    concatenateHeaderData : function concatenateHeaderData(headerList, responseList) {
        var temporaryLength = 0;
        var realdata;
        var headerel, data, tbox;
        var headerLength = JPIP.prototype.getHeaderDataLength(headerList, responseList);
        var fullheaderdata = new Uint8Array(headerLength);
        temporaryLength = 0;
        for (var ii = 0; ii < headerList.length; ii++) {
            headerel = headerList[ii];
            data = responseList[headerel.responseListIndex];
            tbox = readUint32(data, headerel.beginOffsetNoHeader + 4);
            if (tbox != 221885891684) {
                realdata = data.subarray(headerel.beginOffsetNoHeader, headerel.endOffsetNoHeader);
            } else {
                realdata = data.subarray(headerel.beginOffsetNoHeader + 20, headerel.beginOffsetNoHeader + 28);
            }
            fullheaderdata.set(realdata, temporaryLength);
            temporaryLength += realdata.length;
        }
        return fullheaderdata;
    },
    readVBAS : function readVBAS(responseList, offset, vbasDict, key) {
        var length = 0;
        do {
            b = readUINT8(responseList[offset]);
            for (var i = 1; i < 8; i++) {
                length = (length << 1) + b[i];
            }
            offset++;
        } while (b[0] == 1);
        vbasDict[key] = length;
        return offset;
    }
};
