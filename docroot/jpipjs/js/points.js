solarEvent = function(angles, imageTextureIndex) {
    this.L0 = angles[0];
    this.B0 = angles[1];
    this.imageTextureIndex = imageTextureIndex;
}

sunPoints = function() {
    this.initialized = false;
    this.textures = [ "./images/EventIcons/ar_icon.png" ];
    this.images = {};
    this.imageTextures = {};
    this.amountOfTexturesLoaded = 0;
    this.texturesLoaded = false;
    this.textureLoadStarted = false;
    this.supportedModes = [ '2D', '3D' ];// , '3D', 'limb', 'limb-conformal'
    this.optionsPanel = document.createElement("div");
    this.optionsPanel.innerHTML = "<div>TBD</div>";
    core.gui.addTypePanel("sunPoints");
    core.gui.addLayer("sunPoints", "SUNP", this.optionsPanel);
    this.viewportIndices = [ 0, 1, 2, 3 ];
    this.verticesBuffer = {};
    this.textureCoordsBuffer = {};
    this.solarEvents = [];
    var angs = [ [ 0., 0. ], [ Math.PI / 2., 0. ], [ Math.PI, 0. ], [ 3 * Math.PI / 2., 0. ], [ 0., Math.PI / 4. ] ];

    for (var i = 0; i < angs.length; i++) {
        var event = new solarEvent(angs[i], this.textures.indexOf("./images/EventIcons/ar_icon.png"));
        this.solarEvents.push(event);
    }

    this.init();
}

sunPoints.prototype.init = function(gl) {
    ref = this;
    onsuccess = function(data) {
        for (var i = 0; i < data.result.length; i++) {
            var event = new solarEvent([ data.event_coords1 / 180. * Math.PI, data.event_coord2 / 180. * Math.PI ], ref.textures.indexOf("./images/EventIcons/ar_icon.png"));
            ref.solarEvents.push(event);
        }
        console.log()
    };
    onfail = function(data) {
        console.log(data)
    };
    // getJSON("http://www.lmsal.com/hek/her?cosec=2&cmd=search&type=column&event_type=ar,fl,ef&event_starttime=2015-01-24T00:00:00&event_endtime=2015-01-25T00:00:00&event_coordsys=helioprojective&x1=-1200&x2=1200&y1=-1200&y2=1200&&param0=FRM_Name&op0==&value0=NOAA%20SWPC%20Observer",
    // onsuccess, onfail);
    if (!this.textureLoadStarted) {
        var ref = this;
        for (var i = 0; i < this.textures.length; i++) {
            var image = new Image();
            image["index"] = i;
            image.onload = function() {
                ref.images[image["index"]] = image;
                ref.texturesLoaded = true;
                ref.amountOfTexturesLoaded++;
                if (ref.amountOfTexturesLoaded === ref.textures.length) {
                    ref.texturesLoaded = true;
                }
            }
            image.src = this.textures[i];
        }
        this.textureLoadStarted = true;
    }
    if (!this.initialized && this.texturesLoaded) {
        this.loadTextures(gl);
        this.loadGUIElements();
        for (var i = 0; i < this.supportedModes.length; i++) {
            this.initShaders(gl, this.supportedModes[i]);
        }
        for (var i = 0; i < this.supportedModes.length; i++) {
            this.initBuffers(gl, this.supportedModes[i]);
        }
        for (var i = 0; i < this.supportedModes.length; i++) {
            this.initShaders(gl, this.supportedModes[i]);
        }
        this.initialized = true;
    }
}

sunPoints.prototype.loadTextures = function(gl) {
    for (var i = 0; i < this.textures.length; i++) {
        this.imageTextures[i] = gl.createTexture();
        gl.bindTexture(gl.TEXTURE_2D, this.imageTextures[i]);
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, this.images[i]);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.NEAREST);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.NEAREST);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
        gl.bindTexture(gl.TEXTURE_2D, null);
    }
}

sunPoints.prototype.loadGUIElements = function() {

}
sunPoints.prototype.initBuffers = function(gl, key) {
    this.verticesBuffer[key] = gl.createBuffer();
    var f = 0.05;
    this.vertices = new Float32Array([ f, f, f, -f, -f, -f, -f, -f, -f, f, f, f ]);
    gl.bindBuffer(gl.ARRAY_BUFFER, this.verticesBuffer[key]);
    gl.bufferData(gl.ARRAY_BUFFER, this.vertices, gl.DYNAMIC_DRAW);

    this.textureCoordsBuffer[key] = gl.createBuffer();
    var h = 1.;
    this.textureCoords = new Float32Array([ h, 0, h, h, 0, h, 0, h, 0, 0, h, 0 ]);
    gl.bindBuffer(gl.ARRAY_BUFFER, this.textureCoordsBuffer[key]);
    gl.bufferData(gl.ARRAY_BUFFER, this.textureCoords, gl.DYNAMIC_DRAW);
}

sunPoints.prototype.initShaders = function(gl, key) {

    if (sunPoints.prototype.shaderProgram === undefined) {
        sunPoints.prototype.shaderProgram = {};
        sunPoints.prototype.vertexPositionAttribute = {};
        sunPoints.prototype.textureCoordsAttribute = {};
    }
    if (sunPoints.prototype.shaderProgram[key] !== undefined) {
        return;
    }
    console.log("shader-fs-" + key);
    var fragmentShader = getShader(gl, "shader-fs-sunpoints-" + key);
    var vertexShader = getShader(gl, "shader-vs-sunpoints-" + key);

    sunPoints.prototype.shaderProgram[key] = gl.createProgram();
    gl.attachShader(sunPoints.prototype.shaderProgram[key], vertexShader);
    gl.attachShader(sunPoints.prototype.shaderProgram[key], fragmentShader);
    gl.linkProgram(sunPoints.prototype.shaderProgram[key]);

    if (!gl.getProgramParameter(sunPoints.prototype.shaderProgram[key], gl.LINK_STATUS)) {
        alert("Unable to initialize the shader program.");
    }

    gl.useProgram(sunPoints.prototype.shaderProgram[key]);

    sunPoints.prototype.vertexPositionAttribute[key] = gl.getAttribLocation(sunPoints.prototype.shaderProgram[key], "aVertexPosition");
    gl.enableVertexAttribArray(sunPoints.prototype.vertexPositionAttribute[key]);

    sunPoints.prototype.textureCoordsAttribute[key] = gl.getAttribLocation(sunPoints.prototype.shaderProgram[key], "aTextureCoords");
    gl.enableVertexAttribArray(sunPoints.prototype.textureCoordsAttribute[key]);
}

sunPoints.prototype.prerender = function(gl) {

}
sunPoints.prototype.extendBackwards = function(gl) {

}
sunPoints.prototype.computeMatrix = function(L0, B0, mvMatrix) {
    var M1 = Matrix.Rotation(B0, $V([ 1, 0, 0 ]));
    var M2 = Matrix.Rotation(L0, $V([ 0, 1, 0 ]));
    var V = $V([ 0., 0., 1., 1. ]);
    var M = M2.x(M1).ensure4x4();
    var V = M.x(V);

    var MM = mvMatrix.x(Matrix.Translation($V([ V.elements[0], V.elements[1], V.elements[2] ])));

    MM = MM.x(M);
    return MM;
}
sunPoints.prototype.render = function(gl, mvMatrix, time, viewportIndex) {
    var key = core.viewport.viewportDetails[viewportIndex].mode;
    gl.useProgram(sunPoints.prototype.shaderProgram[key]);

    if (this.initialized) {
        gl.enable(gl.BLEND);
        gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);
        gl.enableVertexAttribArray(this.textureCoordsAttribute[key]);
        gl.enableVertexAttribArray(this.vertexPositionAttribute[key]);
        {
            var vpDetail = core.viewport.viewportDetails[viewportIndex];
            this.solarEvents[this.solarEvents.length - 1].L0 = vpDetail.L0click;
            this.solarEvents[this.solarEvents.length - 1].B0 = vpDetail.B0click;

            for (var i = 0; i < this.solarEvents.length; i++) {
                var event = this.solarEvents[i];
                var MM = this.computeMatrix(event.L0, event.B0, mvMatrix)
                var mvUniform = gl.getUniformLocation(sunPoints.prototype.shaderProgram[key], "uMVMatrix");
                gl.uniformMatrix4fv(mvUniform, false, new Float32Array(MM.flatten()));
                gl.activeTexture(gl.TEXTURE0);
                gl.bindTexture(gl.TEXTURE_2D, this.imageTextures[event.imageTextureIndex]);
                gl.uniform1i(gl.getUniformLocation(this.shaderProgram[key], "uSampler"), 0);

                gl.bindBuffer(gl.ARRAY_BUFFER, this.textureCoordsBuffer[key]);
                gl.vertexAttribPointer(this.textureCoordsAttribute[key], 2, gl.FLOAT, false, 0, 0);
                gl.bindBuffer(gl.ARRAY_BUFFER, this.verticesBuffer[key]);
                gl.bufferData(gl.ARRAY_BUFFER, this.vertices, gl.DYNAMIC_DRAW);

                gl.vertexAttribPointer(this.vertexPositionAttribute[key], 2, gl.FLOAT, false, 0, 0);

                gl.drawArrays(gl.TRIANGLES, 0, 6);
            }
        }
        gl.disableVertexAttribArray(this.vertexPositionAttribute[key]);
        gl.disableVertexAttribArray(this.textureCoordsAttribute[key]);
        gl.disable(gl.BLEND);
    }
}
sunPoints.prototype.updateGUI = function() {

}