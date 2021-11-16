function solarJPIP(baseurl, imgname, newNumberOfFrames, size, sourceId, beginDate, endDate) {
    this.sourceId = sourceId;
    this.beginDate = beginDate;
    this.endDate = endDate;
    this.metadataInitialized = false;
    this.initialized = false;
    this.visible = true;
    this.baseurl = baseurl;
    this.imgname = imgname;
    this.texturesAndMetadata = []
    this.parsedMetadata = [];
    this.plottingMetadata = [];
    this.textureData = [];
    this.verticesBuffer;
    this.verticesTextureCoordBuffer;
    this.verticesIndexBuffer;
    this.shaderProgram;
    this.currentIndex = 0;
    this.newNumberOfFrames = newNumberOfFrames;
    this.numberOfFrames = 0;
    this.size = size;
    this.colormapTexture;
    this.colormapImage;
    this.colormapInitialized = false;
    this.colorTableValue = {};
    this.boostboxValue = {};
    this.isDiff = {};
    this.alphaValue = {};

    for (var i = 0; i < 16; i++) {
        this.colorTableValue[i] = 1.;
        this.boostboxValue[i] = 0.8;
        this.isDiff[i] = 0;
        this.alphaValue[i] = 1.;
    }
    this.infoDiv;
    this.viewportIndices = [ 0 ];
    this.optionsPanel = document.createElement("div");
    this.metadataPanel;
    this.supportedModes = [ '2D', '3D', 'limb', 'limb-conformal' ];
    core.viewport.addListener(this);
    core.gui.addLayer("solarJPIP", sourceId, this.optionsPanel);
    this.viewportDetailDiv = {};
    this.viewportDetails = document.createElement("div");
    this.optionsPanel.appendChild(this.viewportDetails);
    this.comboViewportmap = {};
}

solarJPIP.prototype.buildUrl = function(beginDateString, endDateString) {
    var url = core.gui.datasetGUIObject.baseurl;
    url += "&sourceId=";
    url += this.sourceId;
    url += "&startTime=";
    url += beginDateString + ".000Z";
    url += "&endTime=";
    url += endDateString + ".000Z";
    url += "&cadence=1800&jpip=true&verbose=true&linked=true";
    return url;
}

solarJPIP.prototype.extendBackwards = function() {
    var ttt = this;
    var success = function(data) {
        var jpxfile = data.uri;
        var jpxparts = jpxfile.split("movies");
        ttt.baseurl = globalProtocol + jpxparts[0].substring(4, jpxparts[0].length);
        ttt.imgname = "movies" + jpxparts[1];
        core.beginDate = ttt.beginDate - (ttt.endDate - ttt.beginDate);
        this.beginDate = ttt.beginDate - (ttt.endDate - ttt.beginDate);
        ttt.initTextures(data.frames.length)
    };
    getJSON(this.buildUrl(formatDate(new Date(this.beginDate - (this.endDate - this.beginDate))), formatDate(new Date(this.beginDate))), success, function(e) {
    });
}

solarJPIP.prototype.render = function(gl, mvMatrix, time, viewportIndex) {
    if (solarJPIP.prototype.shaderProgram === undefined) {
        return;
    }
    var key = core.viewport.viewportDetails[viewportIndex].mode;
    gl.useProgram(solarJPIP.prototype.shaderProgram[key]);
    gl.enableVertexAttribArray(solarJPIP.prototype.vertexPositionAttribute[key]);
    {
        if (this.parsedMetadata.length > 0) {
            this.currentIndex = this.binarySearch(time);
        }
        if (this.texturesAndMetadata[this.currentIndex] === undefined) {
            this.currentIndex = 0;
        }
        if (this.initialized && this.visible && this.texturesAndMetadata[this.currentIndex] !== undefined) {
            gl.enable(gl.BLEND);
            gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);
            gl.bindBuffer(gl.ARRAY_BUFFER, this.verticesBuffer[key]);
            gl.vertexAttribPointer(this.vertexPositionAttribute[key], 3, gl.FLOAT, false, 0, 0);

            gl.activeTexture(gl.TEXTURE0);
            gl.bindTexture(gl.TEXTURE_2D, this.texturesAndMetadata[this.currentIndex].texture);

            if (this.isDiff[viewportIndex] && this.texturesAndMetadata[this.currentIndex + 1] !== undefined) {
                gl.activeTexture(gl.TEXTURE1);
                gl.bindTexture(gl.TEXTURE_2D, this.texturesAndMetadata[this.currentIndex + 1].texture);
                gl.uniform2f(gl.getUniformLocation(this.shaderProgram[key], "centerDiff"), this.texturesAndMetadata[this.currentIndex + 1].plottingMetadata.x0, this.texturesAndMetadata[this.currentIndex + 1].plottingMetadata.y0);
                gl.uniform2f(gl.getUniformLocation(this.shaderProgram[key], "stretchDiff"), this.texturesAndMetadata[this.currentIndex + 1].plottingMetadata.solarRadiiX, this.texturesAndMetadata[this.currentIndex + 1].plottingMetadata.solarRadiiY);
            } else {
                gl.activeTexture(gl.TEXTURE1);
                gl.bindTexture(gl.TEXTURE_2D, this.texturesAndMetadata[this.currentIndex].texture);
                gl.uniform2f(gl.getUniformLocation(this.shaderProgram[key], "centerDiff"), this.texturesAndMetadata[this.currentIndex].plottingMetadata.x0, this.texturesAndMetadata[this.currentIndex].plottingMetadata.y0);
                gl.uniform2f(gl.getUniformLocation(this.shaderProgram[key], "stretchDiff"), this.texturesAndMetadata[this.currentIndex].plottingMetadata.solarRadiiX, this.texturesAndMetadata[this.currentIndex].plottingMetadata.solarRadiiY);
            }
            gl.uniform1i(gl.getUniformLocation(this.shaderProgram[key], "isDiff"), this.isDiff[viewportIndex]);

            gl.activeTexture(gl.TEXTURE2);
            gl.bindTexture(gl.TEXTURE_2D, this.colormapTexture);

            gl.uniform1i(gl.getUniformLocation(this.shaderProgram[key], "uSampler"), 0);
            gl.uniform1i(gl.getUniformLocation(this.shaderProgram[key], "uSamplerDiff"), 1);

            gl.uniform1i(gl.getUniformLocation(this.shaderProgram[key], "uColormap"), 2);
            gl.uniform1f(gl.getUniformLocation(this.shaderProgram[key], "colorTableValue"), this.colorTableValue[viewportIndex]);
            gl.uniform1f(gl.getUniformLocation(this.shaderProgram[key], "boostboxValue"), 1. - this.boostboxValue[viewportIndex]);
            gl.uniform1f(gl.getUniformLocation(this.shaderProgram[key], "alphaValue"), this.alphaValue[viewportIndex]);

            gl.uniform2f(gl.getUniformLocation(this.shaderProgram[key], "center"), this.texturesAndMetadata[this.currentIndex].plottingMetadata.x0, this.texturesAndMetadata[this.currentIndex].plottingMetadata.y0);
            gl.uniform2f(gl.getUniformLocation(this.shaderProgram[key], "stretch"), this.texturesAndMetadata[this.currentIndex].plottingMetadata.solarRadiiX, this.texturesAndMetadata[this.currentIndex].plottingMetadata.solarRadiiY);

            gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, this.verticesIndexBuffer[key]);

            var mvUniform = gl.getUniformLocation(this.shaderProgram[key], "uMVMatrix");

            var vpDetail = core.viewport.viewportDetails[viewportIndex];
            lmvMatrix = mvMatrix.x(vpDetail.rotationMatrix);
            gl.uniformMatrix4fv(mvUniform, false, new Float32Array(lmvMatrix.flatten()));

            if (key === '3D') {
                var mvUniform = gl.getUniformLocation(this.shaderProgram[key], "uMVMatrix");
                gl.uniformMatrix4fv(mvUniform, false, new Float32Array(mvMatrix.flatten()));
                var theta = this.texturesAndMetadata[this.currentIndex].plottingMetadata.hgltobs * 3.141592654 / 180.;
                var phi = this.texturesAndMetadata[this.currentIndex].plottingMetadata.hglnobs * 3.141592654 / 180.;
                var rotmat = $M([ [ Math.cos(phi), 0, -Math.sin(phi) ], [ -Math.sin(theta) * Math.sin(phi), Math.cos(theta), -Math.sin(theta) * Math.cos(phi) ], [ Math.cos(theta) * Math.sin(phi), Math.sin(theta), Math.cos(theta) * Math.cos(phi) ] ]);
                var rotmatinv = rotmat.inverse();
                var rotmatUniform = gl.getUniformLocation(this.shaderProgram[key], "rotmat");
                var rotmatUniformInv = gl.getUniformLocation(this.shaderProgram[key], "rotmatinv");

                gl.uniformMatrix3fv(rotmatUniform, false, new Float32Array(rotmat.flatten()));
                gl.uniformMatrix3fv(rotmatUniformInv, false, new Float32Array(rotmatinv.flatten()));

                gl.drawElements(gl.TRIANGLES, this.vertexIndices[key].length, gl.UNSIGNED_SHORT, 0);
            } else {
                gl.drawElements(gl.TRIANGLES, 6, gl.UNSIGNED_SHORT, 0);
            }
            gl.disable(gl.BLEND);
        }
    }
    gl.disableVertexAttribArray(solarJPIP.prototype.vertexPositionAttribute[key]);
}
solarJPIP.prototype.prerender = function(gl) {
    this.loadNewTextures(gl);
}
solarJPIP.prototype.updateGUI = function() {
    if (this.texturesAndMetadata[this.currentIndex] !== undefined) {
        this.metadataPanel.innerHTML = this.texturesAndMetadata[this.currentIndex].parsedMetadata;
    }
}
solarJPIP.prototype.init = function(gl) {
    if (!this.colormapInitialized) {
        var image = new Image();
        var ref = this;
        image.onload = function() {
            ref.colormapImage = image;
            ref.colormapInitialized = true;
        }
        image.src = "./images/gradient_jhv.png";
    }
    if (!this.initialized && this.colormapInitialized) {
        this.loadColormapTexture(gl);
        this.loadGUIElements();
        for (var i = 0; i < this.supportedModes.length; i++) {
            this.initShaders(gl, this.supportedModes[i]);
        }
        for (var i = 0; i < this.supportedModes.length; i++) {
            this.initBuffers(gl, this.supportedModes[i]);
        }
        this.initTextures(this.newNumberOfFrames);
        this.initialized = true;
    }
}
solarJPIP.prototype.initBuffers = function(gl, key) {
    this.initVerticesBuffers(gl, key);
    // this.initTextureCoordBuffers(gl);
}
solarJPIP.prototype.initVerticesBuffers = function(gl, key) {
    if (this.verticesBuffer === undefined) {
        this.verticesBuffer = {};
        this.verticesIndexBuffer = {};
        this.vertexIndices = {};
    }
    this.verticesBuffer[key] = gl.createBuffer();
    this.vertexIndices[key] = [ 0, 1, 2, 0, 2, 3, ];
    var vertices = [ -5.0, -5.0, 0.0, -5.0, 5.0, 0.0, 5.0, 5.0, 0.0, 5.0, -5.0, 0.0, ];
    if (key !== '3D') {
        vertices = [ -1.0, -1.0, 0.0, -1.0, 1.0, 0.0, 1.0, 1.0, 0.0, 1.0, -1.0, 0.0, ];
        this.vertexIndices[key] = [ 0, 1, 2, 0, 2, 3, ];
    } else {
        var res = 30;
        var count = 0;
        for (var i = 0; i < res - 1; i++) {
            for (var j = 0; j < res - 1; j++) {
                for (var face = 0; face <= 2; face++) {
                    for (var sign = -1; sign <= 1; sign = sign + 2) {
                        p1 = this.sphericalPoint(i, j, res, face, sign);
                        p2 = this.sphericalPoint(i, j + 1, res, face, sign);
                        p3 = this.sphericalPoint(i + 1, j, res, face, sign);
                        p4 = this.sphericalPoint(i + 1, j + 1, res, face, sign);
                        var cp1 = count;
                        vertices.push(p1.x);
                        vertices.push(p1.y);
                        vertices.push(p1.z);
                        count++;
                        var cp2 = count;
                        vertices.push(p2.x);
                        vertices.push(p2.y);
                        vertices.push(p2.z);
                        count++;
                        var cp3 = count;
                        vertices.push(p3.x);
                        vertices.push(p3.y);
                        vertices.push(p3.z);
                        count++;
                        var cp4 = count;
                        vertices.push(p4.x);
                        vertices.push(p4.y);
                        vertices.push(p4.z);
                        count++;
                        if (sign == -1) {
                            this.vertexIndices[key].push(cp1);
                            this.vertexIndices[key].push(cp2);
                            this.vertexIndices[key].push(cp3);

                            this.vertexIndices[key].push(cp2);
                            this.vertexIndices[key].push(cp4);
                            this.vertexIndices[key].push(cp3);
                        } else {
                            this.vertexIndices[key].push(cp1);
                            this.vertexIndices[key].push(cp3);
                            this.vertexIndices[key].push(cp2);

                            this.vertexIndices[key].push(cp2);
                            this.vertexIndices[key].push(cp4);
                            this.vertexIndices[key].push(cp3);
                        }
                    }
                }
            }
        }
    }

    gl.bindBuffer(gl.ARRAY_BUFFER, this.verticesBuffer[key]);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(vertices), gl.STATIC_DRAW);
    this.verticesIndexBuffer[key] = gl.createBuffer();
    gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, this.verticesIndexBuffer[key]);
    gl.bufferData(gl.ELEMENT_ARRAY_BUFFER, new Uint16Array(this.vertexIndices[key]), gl.STATIC_DRAW);
}
solarJPIP.prototype.initTextureCoordBuffers = function(gl) {
    this.verticesTextureCoordBuffer = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, this.verticesTextureCoordBuffer);
    var textureCoordinates = [ 0.0, 1.0, 0.0, 0.0, 1.0, 0.0, 1.0, 1.0, ];
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(textureCoordinates), gl.STATIC_DRAW);
}
solarJPIP.prototype.initIndicesBuffers = function(gl) {
}

solarJPIP.prototype.initTextures = function(nof) {
    var toAdd = this.numberOfFrames;
    this.numberOfFrames += nof;
    var td = this;
    JPIP.prototype.onload = function(data) {
        if (data[0] == "meta") {
            var metadata = data[5];
            td.parseXML(metadata);
            td.metadataInitialized = true;
        }
        var curr = data[4];
        dataObj = {};
        dataObj.data = new Uint8Array(data[1]);
        dataObj.width = data[2];
        dataObj.height = data[3];
        dataObj.index = curr + toAdd;
        td.textureData.push(dataObj);
    }
    var jpip = new JPIP();
    var jpipConn = jpip.open(this.baseurl, this.imgname, this.size, nof);
}

solarJPIP.prototype.loadColormapTexture = function(gl) {
    this.colormapTexture = gl.createTexture();
    gl.bindTexture(gl.TEXTURE_2D, this.colormapTexture);
    gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, this.colormapImage);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.NEAREST);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.NEAREST);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
    gl.bindTexture(gl.TEXTURE_2D, null);
}

solarJPIP.prototype.loadNewTextures = function(gl) {
    if (this.textureData.length > 0 && this.metadataInitialized) {
        image = this.textureData.pop();
        var texture = gl.createTexture();
        gl.bindTexture(gl.TEXTURE_2D, texture);
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.LUMINANCE, image.width, image.height, 0, gl.LUMINANCE, gl.UNSIGNED_BYTE, image.data);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR_MIPMAP_NEAREST);
        gl.generateMipmap(gl.TEXTURE_2D);
        gl.bindTexture(gl.TEXTURE_2D, null);
        var tAndM = {};
        tAndM.texture = texture;
        tAndM.parsedMetadata = this.parsedMetadata[image.index];
        tAndM.plottingMetadata = this.plottingMetadata[image.index];

        this.texturesAndMetadata.push(tAndM);
        this.texturesAndMetadata.sort(function(a, b) {
            return (a.plottingMetadata.dateObs - b.plottingMetadata.dateObs);
        });

        this.infoDiv.innerHTML = "Loaded " + this.texturesAndMetadata.length + "/" + (this.parsedMetadata.length - 1);
    }
}

solarJPIP.prototype.initShaders = function(gl, key) {

    if (solarJPIP.prototype.shaderProgram === undefined) {
        solarJPIP.prototype.shaderProgram = {};
        solarJPIP.prototype.vertexPositionAttribute = {};
        solarJPIP.prototype.textureCoordAttribute = {};
    }
    if (solarJPIP.prototype.shaderProgram[key] !== undefined) {
        return;
    }
    console.log("shader-fs-" + key);
    var fragmentShader = getShader(gl, "shader-fs-" + key);
    var vertexShader = getShader(gl, "shader-vs-" + key);

    solarJPIP.prototype.shaderProgram[key] = gl.createProgram();
    gl.attachShader(solarJPIP.prototype.shaderProgram[key], vertexShader);
    gl.attachShader(solarJPIP.prototype.shaderProgram[key], fragmentShader);
    gl.linkProgram(solarJPIP.prototype.shaderProgram[key]);

    if (!gl.getProgramParameter(solarJPIP.prototype.shaderProgram[key], gl.LINK_STATUS)) {
        alert("Unable to initialize the shader program.");
    }

    gl.useProgram(solarJPIP.prototype.shaderProgram[key]);

    solarJPIP.prototype.vertexPositionAttribute[key] = gl.getAttribLocation(solarJPIP.prototype.shaderProgram[key], "aVertexPosition");
    // gl.enableVertexAttribArray(solarJPIP.prototype.vertexPositionAttribute[key]);

    // solarJPIP.prototype.textureCoordAttribute[key] =
    // gl.getAttribLocation(solarJPIP.prototype.shaderProgram[key],
    // "aTextureCoord");
    // gl.enableVertexAttribArray(solarJPIP.prototype.textureCoordAttribute[key]);
}

solarJPIP.prototype.parseXML = function(metadata) {
    for (var i = 0; i < metadata.length; i++) {
        xmlString = metadata[i];
        if (xmlString[xmlString.length - 1] == "\0") {
            xmlString = xmlString.substring(0, xmlString.length - 1);
        }
        if (window.DOMParser) {
            parser = new DOMParser();
            xmlDoc = parser.parseFromString(xmlString, "text/xml");
        } else // IE
        {
            xmlDoc = new ActiveXObject("Microsoft.XMLDOM");
            xmlDoc.async = false;
            xmlDoc.loadXML(xmlString);
        }
        keywords = {};
        var fitsElementsNode = xmlDoc.getElementsByTagName("fits");
        numberOfKeywords = 0;
        var helpNN = fitsElementsNode[0].childNodes;
        for (var j = 0; j < fitsElementsNode[0].childNodes.length; j++) {
            vv = helpNN[j].nodeName;
            try {
                if (vv != "#text" && helpNN[j] !== undefined) {
                    keywords[helpNN[j].nodeName] = " " + helpNN[j].childNodes[0].nodeValue;
                    numberOfKeywords++;
                }
            } catch (err) {
                console.log("ERROR in META");
            }
        }
        if (keywords["DATE-OBS"] === undefined && keywords["DATE_OBS"] !== undefined) {
            keywords["DATE-OBS"] = keywords["DATE_OBS"];
        }
        this.parsedMetadata.push(printMetadata(keywords));
        var plotMetadata = {};
        plotMetadata.crpix1 = parseFloat(keywords["CRPIX1"], 10) - 1;
        plotMetadata.crpix2 = parseFloat(keywords["CRPIX2"], 10) - 1;
        plotMetadata.dsunObsMeters = parseFloat(keywords["DSUN_OBS"]);
        plotMetadata.dsunObsKilometers = plotMetadata.dsunObsMeters / 1000.;
        var naxis1 = parseInt(keywords["NAXIS1"]);
        var naxis2 = parseInt(keywords["NAXIS2"]);
        var cdelt1 = parseFloat(keywords["CDELT1"]);
        var cdelt2 = parseFloat(keywords["CDELT2"]);
        var rad1 = arcsecondsToRadians(cdelt1 * naxis1 / 2.);
        var rad2 = arcsecondsToRadians(cdelt2 * naxis2 / 2.);

        plotMetadata.meterX = Math.tan(rad1) * plotMetadata.dsunObsMeters;
        plotMetadata.meterY = Math.tan(rad2) * plotMetadata.dsunObsMeters;
        plotMetadata.solarRadiiX = plotMetadata.meterX / solarConstants.radiusMeter;
        plotMetadata.solarRadiiY = plotMetadata.meterY / solarConstants.radiusMeter;
        plotMetadata.x0 = (plotMetadata.crpix1 - 0.5) / naxis1 - 0.5;
        plotMetadata.y0 = (plotMetadata.crpix2 - 0.5) / naxis2 - 0.5;
        try {
            plotMetadata.dateObs = parseDate(keywords["DATE-OBS"], 1);
        } catch (err) {
            if (keywords["DATE-OBS"] !== undefined) {
                console.log(keywords["DATE-OBS"]);
            } else {
                console.log("DATE-OBS not defined");
            }
            console.log("ERROR parsing metadata:" + err);
        }
        try {
            plotMetadata.hgltobs = parseFloat(keywords["CRLT_OBS"]);
        } catch (err) {
            try {
                plotMetadata.hgltobs = parseFloat(keywords["HGLT-OBS"]);
            } catch (err) {
                plotMetadata.hgltobs = 0;
            }
        }
        try {
            plotMetadata.hglnobs = parseFloat(keywords["CRLN_OBS"]);
        } catch (err) {
            try {
                plotMetadata.hglnobs = parseFloat(keywords["HGLN-OBS"]);
            } catch (err) {
                plotMetadata.hglnobs = 0;
            }
        }
        this.plottingMetadata.push(plotMetadata);
    }
}

solarJPIP.prototype.binarySearch = function(time) {
    var array = this.texturesAndMetadata;
    var arrlen = array.length;
    var foundindex = 0;
    while (foundindex < arrlen) {
        if (array[foundindex].plottingMetadata.dateObs < time) {
            foundindex++;
        } else {
            break;
        }
    }
    if (foundindex == arrlen) {
        foundindex--;
    }
    return foundindex;
}
function printMetadata(keywords) {
    var metadataHtml = "<ul>";
    var keys = [];
    for ( var key in keywords) {
        keys.push(key);
    }
    keys.sort();
    for (var i = 0; i < keys.length; i++) {
        if (this.keywords[keys[i]].length < 40) {
            // if (keys[i][0] == "D" || keys[i][0] == "C") {
            metadataHtml += "<li>" + keys[i] + " : " + this.keywords[keys[i]] + "</li>";
            // }
        }
    }
    metadataHtml += "<li>" + "test" + "</li>";
    metadataHtml += "</ul>";
    return metadataHtml;
}
// GUI section
solarJPIP.prototype.loadGUIElements = function(e) {
    this.infoDiv = document.createElement("div");
    this.infoDiv.setAttribute("class", "solarjpipinfodiv");
    this.optionsPanel.appendChild(this.infoDiv);
    this.loadMetadataPanel();
    this.fireViewportChanged(core.viewport);
}

solarJPIP.prototype.handleEvent = function(e) {
    switch (e.type) {
        case "change":
            var element = e.target || e.srcElement;
            var elementType = element.attributes["data-type"].value;
            if (elementType == "comboColormap") {
                var elementViewport = parseInt(element.attributes["data-viewport"].value);
                this.colormapSelected(e, elementViewport);
            } else if (elementType == "checkboxViewport") {
                var elementViewport = parseInt(element.attributes["data-viewport"].value);
                this.handleViewportcheckBox(element, elementViewport);
            } else if (elementType == "checkboxDifference") {
                var elementViewport = parseInt(element.attributes["data-viewport"].value);
                if (element.checked) {
                    this.isDiff[elementViewport] = 1;
                } else {
                    this.isDiff[elementViewport] = 0;
                }
            } else if (elementType == "checkboxMetadata") {
                if (element.checked) {
                    this.metadataPanel.style.visibility = 'visible';
                    this.metadataPanel.style.display = 'inline'
                } else {
                    this.metadataPanel.style.visibility = 'hidden';
                    this.metadataPanel.style.display = 'none';
                }
            } else if (elementType == "boostboxDifference") {
                var elementViewport = parseInt(element.attributes["data-viewport"].value);
                this.boostboxValue[elementViewport] = element.value;
            } else if (elementType == "alphabox") {
                var elementViewport = parseInt(element.attributes["data-viewport"].value);
                this.alphaValue[elementViewport] = element.value;
            }
    }
}
solarJPIP.prototype.handleViewportcheckBox = function(element, elementViewport) {
    if (element.checked) {
        this.viewportIndices.push(elementViewport);
    } else {
        var idx = this.viewportIndices.indexOf(elementViewport);
        if (idx !== -1) {
            this.viewportIndices.splice(idx, 1);
        }
    }
}

solarJPIP.prototype.colormapSelected = function(e, elementViewport) {
    var comboColormap = e.target || e.srcElement;
    var selectedIndex = comboColormap.selectedIndex;
    this.colorTableValue[elementViewport] = comboColormap.children[selectedIndex].value;
}

solarJPIP.prototype.loadColormapGui = function(number, viewportDetailDiv) {
    var colormapDiv = document.createElement("div");
    colormapDiv.setAttribute('id', "colormapDiv" + number);
    viewportDetailDiv.appendChild(colormapDiv);
    var colorTableNames = [ 'Blue/Green/Red/Yellow', 'Blue/Red', 'Blue/White Linear', 'Gray', 'Green/White Exponential', 'Green/White Linear', 'Rainbow 1', 'Rainbow 2', 'Red Temperature', 'SDO-AIA 131', 'SDO-AIA 1600', 'SDO-AIA 1700', 'SDO-AIA 171', 'SDO-AIA 193', 'SDO-AIA 211', 'SDO-AIA 304', 'SDO-AIA 335', 'SDO-AIA 4500', 'SDO-AIA 94', 'SOHO EIT 171', 'SOHO EIT 195', 'SOHO EIT 284', 'SOHO EIT 304', 'STEREO EUVI 171', 'STEREO EUVI 195', 'STEREO EUVI 284', 'STEREO EUVI 304' ];
    var comboColormap = document.createElement("select");
    comboColormap.setAttribute("class", "comboColormap");
    for (var i = 0; i < colorTableNames.length; i++) {
        var comboColormapOption = document.createElement("option");
        comboColormapOption.setAttribute("value", (i + 0.5) / 256.);
        comboColormapOption.innerHTML = colorTableNames[i];
        comboColormap.appendChild(comboColormapOption);
        if (i === 3) {
            comboColormapOption.setAttribute("selected", true);
        }
    }
    var comboColormapLabel = document.createElement("label");
    comboColormapLabel.innerHTML = "Colormap:";
    colormapDiv.appendChild(comboColormapLabel);
    colormapDiv.appendChild(comboColormap);
    colormapDiv.appendChild(document.createElement("br"));
    comboColormap.setAttribute("data-type", "comboColormap");
    comboColormap.setAttribute("data-viewport", "" + number);
    comboColormap.addEventListener("change", this, false);
}

solarJPIP.prototype.loadDifferenceCheckbox = function(number, viewportDetailDiv) {
    var differenceCheckboxDiv = document.createElement("div");
    differenceCheckboxDiv.setAttribute("class", "differenceCheckbox");
    viewportDetailDiv.appendChild(differenceCheckboxDiv);
    var differenceCheckboxLabel = document.createElement("label");
    differenceCheckboxLabel.innerHTML = "Difference Image:";

    var differenceBoostboxLabel = document.createElement("label");
    differenceBoostboxLabel.innerHTML = "Boost value:";

    var differenceCheckbox = document.createElement("input");
    differenceCheckbox.setAttribute("type", "checkbox");
    differenceCheckbox.setAttribute("data-type", "checkboxDifference");
    differenceCheckbox.setAttribute("data-viewport", "" + number);

    differenceCheckbox.addEventListener("change", this, false);
    differenceCheckboxDiv.appendChild(differenceCheckboxLabel);
    differenceCheckboxDiv.appendChild(differenceCheckbox);

    var differenceBoostbox = document.createElement("input");
    differenceBoostbox.setAttribute("type", "number");
    differenceBoostbox.setAttribute("data-type", "boostboxDifference");
    differenceBoostbox.setAttribute("data-viewport", "" + number);
    differenceBoostbox.setAttribute("min", 0);
    differenceBoostbox.setAttribute("max", 1);
    differenceBoostbox.setAttribute("step", 0.01);
    differenceBoostbox.value = 0.8;

    differenceBoostbox.addEventListener("change", this, false);
    differenceCheckboxDiv.appendChild(differenceBoostboxLabel);
    differenceCheckboxDiv.appendChild(differenceBoostbox);
    differenceCheckboxDiv.appendChild(document.createElement("br"));
}

solarJPIP.prototype.loadAlphaValue = function(number, viewportDetailDiv) {
    var alphaDiv = document.createElement("div");
    alphaDiv.setAttribute('class', "alphaDiv");
    viewportDetailDiv.appendChild(alphaDiv);
    var alphaLabel = document.createElement("label");
    alphaLabel.innerHTML = "Alpha value:";
    var alphaBox = document.createElement("input");
    alphaBox.setAttribute("type", "number");
    alphaBox.setAttribute("data-type", "alphabox");
    alphaBox.setAttribute("data-viewport", "" + number);
    alphaBox.setAttribute("min", 0);
    alphaBox.setAttribute("max", 1);
    alphaBox.setAttribute("step", 0.01);
    alphaBox.value = 1.;
    alphaBox.addEventListener("change", this, false);
    alphaDiv.appendChild(alphaLabel);
    alphaDiv.appendChild(alphaBox);
    alphaDiv.appendChild(document.createElement("br"));
}

solarJPIP.prototype.loadMetadataPanel = function() {
    this.metadataPanel = document.createElement("div");

    var metadataCheckboxLabel = document.createElement("label");
    metadataCheckboxLabel.innerHTML = "Show metadata: ";

    var metadataCheckbox = document.createElement("input");
    metadataCheckbox.setAttribute("type", "checkbox");
    metadataCheckbox.setAttribute("data-type", "checkboxMetadata");
    metadataCheckbox.addEventListener("change", this, false);
    this.optionsPanel.appendChild(metadataCheckboxLabel);
    this.optionsPanel.appendChild(metadataCheckbox);
    this.optionsPanel.appendChild(document.createElement("br"));

    this.metadataPanel.style.visibility = 'hidden';
    this.metadataPanel.style.display = 'none';
    this.optionsPanel.appendChild(this.metadataPanel);

}
solarJPIP.prototype.loadViewportCheckbox = function(number, viewportDetailDiv) {
    var viewportCheckboxDiv = document.createElement("div");
    viewportCheckboxDiv.setAttribute("class", "viewportCheckbox");
    viewportDetailDiv.appendChild(viewportCheckboxDiv);
    var viewportCheckboxLabel = document.createElement("label");
    viewportCheckboxLabel.innerHTML = "Activate viewport " + number + ":";

    var viewportCheckbox = document.createElement("input");
    viewportCheckbox.setAttribute("type", "checkbox");
    viewportCheckbox.setAttribute("data-type", "checkboxViewport");
    viewportCheckbox.setAttribute("data-viewport", "" + number);
    viewportCheckbox.checked = true;
    this.handleViewportcheckBox(viewportCheckbox, number);

    viewportCheckbox.addEventListener("change", this, false);
    viewportCheckboxDiv.appendChild(viewportCheckboxLabel);
    viewportCheckboxDiv.appendChild(viewportCheckbox);
}

solarJPIP.prototype.loadCombiViewportDetail = function(viewportIndex) {
    if (this.viewportDetailDiv[viewportIndex] === undefined) {
        this.viewportDetailDiv[viewportIndex] = document.createElement("div");
        var viewportDetailDiv = this.viewportDetailDiv[viewportIndex];
        this.viewportDetails.appendChild(viewportDetailDiv);
        this.isDiff[viewportIndex] = false;
        this.boostboxValue[viewportIndex] = 0.8;
        this.optionsPanel.appendChild(viewportDetailDiv);
        this.loadViewportCheckbox(viewportIndex, viewportDetailDiv);
        this.loadDifferenceCheckbox(viewportIndex, viewportDetailDiv);
        this.loadColormapGui(viewportIndex, viewportDetailDiv);
        this.loadAlphaValue(viewportIndex, viewportDetailDiv);
    }
}

solarJPIP.prototype.fireViewportChanged = function(vp) {
    var viewportNames = [];
    for (var j = 0; j < vp.rows * vp.columns; j++) {
        viewportNames.push("" + j);
    }

    for (var k = viewportNames.length; k < 4 * 4; k++) {
        var el = this.viewportDetailDiv[k];
        if (el !== undefined && el !== null) {
            el.parentNode.removeChild(el);
        }
        this.viewportDetailDiv[k] = undefined;
    }
    for (var k = 0; k < viewportNames.length; k++) {
        var el = this.viewportDetailDiv[k];
        if (el === undefined || el === null) {
            this.loadCombiViewportDetail(k);
        }
    }
}

solarJPIP.prototype.sphericalPoint = function(i, j, res, face, sign) {
    point = {};
    if (face === 0) {
        point.x = -1 + 2. * i / (res - 1);
        point.y = -(-1 + 2. * j / (res - 1));
        point.z = sign * Math.sqrt(2. - point.x * point.x - point.y * point.y);
    }
    if (face === 1) {
        point.x = -1. + 2. * i / (res - 1);
        point.z = -(-1. + 2. * j / (res - 1));
        point.y = sign * Math.sqrt(2. - point.x * point.x - point.z * point.z);
    }
    if (face === 2) {
        point.z = -1 + 2. * i / (res - 1);
        point.y = -(-1 + 2. * j / (res - 1));
        point.x = sign * Math.sqrt(2 - point.z * point.z - point.y * point.y);
    }
    var r = Math.sqrt(point.x * point.x + point.y * point.y + point.z * point.z)
    point.x = point.x / r;
    point.y = point.y / r;
    point.z = point.z / r;

    return point;
}