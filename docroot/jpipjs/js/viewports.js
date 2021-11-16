viewportDetail = function(top, left, width, height, mode, index) {
    this.top = top;
    this.left = left;
    this.width = width;
    this.height = height;
    this.zoom = 1.;
    this.mode;
    this.index = index;
    this.projectionMatrix = Matrix.I(4);
    this.viewProjectionMatrix = Matrix.I(4);
    this.mouseMatrix = Matrix.I(4);
    this.translationMatrix = Matrix.I(4);
    this.viewMatrix = Matrix.I(4);
    this.computeProjectionMatrix();
    this.rotationMatrix;
    this.mouseDown = false;
    this.lastPlaneCoordinates;

}

viewportDetail.prototype.computeProjectionMatrix = function() {
    this.projectionMatrix = Matrix.I(4);
    var r = this.zoom;
    var t = this.zoom;
    var f = 100.;
    var n = 0.1;
    this.projectionMatrix.elements[0][0] = 1. / r;
    this.projectionMatrix.elements[1][1] = 1. / t;
    this.projectionMatrix.elements[2][2] = -2. / (f - n);
    this.projectionMatrix.elements[2][3] = -(f + n) / (f - n);
}

viewportDetail.prototype.convertCanvasToViewport = function(canvasCoords) {
    var viewportCoords = $V([ canvasCoords.elements[0] - this.left, canvasCoords.elements[1] - this.top ]);
    return viewportCoords;
}

viewportDetail.prototype.convertViewportToView = function(viewportCoordinates) {
    var vpm = this.projectionMatrix.inverse();
    var solarCoordinates = vpm.multiply($V([ 2. * (viewportCoordinates.elements[0] / this.width - 0.5), -2. * (viewportCoordinates.elements[1] / this.height - 0.5), 0., 0. ]));
    solarCoordinates.elements[3] = 1.;

    solarCoordinates = this.translationMatrix.inverse().x(solarCoordinates);
    solarCoordinates.elements[3] = 0.;

    var solarCoordinates3Dz = Math.sqrt(1 - solarCoordinates.dot(solarCoordinates));
    if (isNaN(solarCoordinates3Dz)) {
        solarCoordinates3Dz = 0.;
    }
    var solarCoordinates3D = solarCoordinates.dup();
    solarCoordinates3D.elements[2] = solarCoordinates3Dz;
    return solarCoordinates3D;
}

viewportDetail.prototype.convertViewportToPlane = function(viewportCoordinates) {
    var vpm = this.projectionMatrix.inverse();
    var planeCoordinates = vpm.multiply($V([ 2. * (viewportCoordinates.elements[0] / this.width - 0.5), -2. * (viewportCoordinates.elements[1] / this.height - 0.5), 0., 0. ]));
    planeCoordinates.elements.pop();
    return planeCoordinates;
}

viewportDetail.prototype.setRotationMatrix = function(mode, date, index) {
    viewportDetail.L0 = getL0Radians(date);
    viewportDetail.B0 = getB0Radians(date);
    var phi = viewportDetail.L0;
    var theta = viewportDetail.B0;
    if (mode === '3D' || mode === '2D') {
        M1 = Matrix.Rotation(theta, $V([ 1, 0, 0 ]));
        M2 = Matrix.Rotation(phi, $V([ 0, 1, 0 ]));
        this.rotationMatrix = M2.x(M1).ensure4x4();
        if (mode == '3D') {
            this.rotationMatrix = this.rotationMatrix.x(this.mouseMatrix);
        }

    } else {
        this.rotationMatrix = Matrix.I(4);
    }
}

viewportDetail.prototype.getViewMatrix = function(mode, date, index) {
    var M, M1, M2, V, Vrot;
    Va = $V([ 0, 0, -10., 1. ]);
    V = $V([ Va.elements[0], Va.elements[1], Va.elements[2] ])

    this.setRotationMatrix(mode, date, index);
    M = this.rotationMatrix;

    if (mode === '3D' || mode === '2D') {
        Vrota = M.x(Va);
        Vrot = $V([ Vrota.elements[0], Vrota.elements[1], Vrota.elements[2] ]);
        M = M.inverse().ensure4x4();
        M = M.x(Matrix.Translation(Vrot));
    } else {
        M = M.x(Matrix.Translation(V).ensure4x4());
    }
    M = this.translationMatrix.x(M);
    return M;
}

viewportDetail.prototype.handleMouseWheel = function() {
    var wheel = event.wheelDelta / 120;// n or -n

    var zoomFactor = 1 + wheel / 2;
    this.zoom = this.zoom * zoomFactor;
    if (this.zoom < 0.1) {
        this.zoom = 0.1;
    }
    if (this.zoom > 5.0) {
        this.zoom = 5.0;
    }
    this.computeProjectionMatrix();
    event.preventDefault();
}

viewportDetail.prototype.handleMouseUp = function(event) {
    this.mouseDown = false;
}

viewportDetail.prototype.handleMouseDown = function(event) {
    this.mouseDown = true;
    var canvasCoordinates = getCanvasCoordinates(event);
    var canvasCoordinates = getCanvasCoordinates(event);
    var viewportCoordinates = this.convertCanvasToViewport(canvasCoordinates);
    var solarCoordinates4D = this.convertViewportToView(viewportCoordinates);
    this.lastSolarCoordinates4D = solarCoordinates4D.dup();
    this.lastPlaneCoordinates = this.convertViewportToPlane(viewportCoordinates);

    solarCoordinates4D = this.rotationMatrix.x(solarCoordinates4D);

    document.getElementById("canvasCoordinates").innerHTML = "" + canvasCoordinates.elements[0] + " " + canvasCoordinates.elements[1];
    document.getElementById("viewportCoordinates").innerHTML = "" + viewportCoordinates.elements[0] + " " + viewportCoordinates.elements[1];

    document.getElementById("solarCoordinates3D").innerHTML = "" + solarCoordinates4D.elements[0] + " " + solarCoordinates4D.elements[1] + " " + solarCoordinates4D.elements[2];

    var lastPhi = Math.atan2(solarCoordinates4D.elements[0], solarCoordinates4D.elements[2]);
    var lastTheta = Math.PI / 2. - Math.acos(solarCoordinates4D.elements[1]);
    this.L0click = lastPhi;
    this.B0click = -lastTheta;

    document.getElementById("thetaPhi").innerHTML = "phi:" + (lastPhi) * 180. / Math.PI + " theta:" + (lastTheta) * 180. / Math.PI;
    document.getElementById("L0B0").innerHTML = "L0:" + (this.L0click) * 180. / Math.PI + " B0:" + (this.B0click) * 180. / Math.PI;

}

viewportDetail.prototype.handleMouseMove = function(event) {
    if (!this.mouseDown) {
        return;
    }
    if (this.mode === '3D') {
        this.handleMouseMove3D(event);
    } else if (this.mode === '2D') {
        this.handleMouseMove2D(event);
    }
}
viewportDetail.prototype.createRotationMatrixFromVectors = function(vec1, vec2) {
    var crossvec = vec1.cross(vec2);
    var dl = Math.sqrt(crossvec.dot(crossvec));
    var mm = null;
    if (!isNaN(dl) && dl !== 0) {
        if (dl > 1.) {
            dl = 1.;
        }
        if (dl < -1.) {
            dl = -1.;
        }
        var a = Math.asin(dl);
        crossvec = crossvec.toUnitVector();
        var mm = Matrix.Rotation(a, crossvec).ensure4x4();
    }
    return mm;
}

viewportDetail.prototype.createTranslationMatrixFromVectors = function(vec1, vec2) {
    var M1 = Matrix.Translation(vec1.subtract(vec2));
    return M1;
}

viewportDetail.prototype.handleMouseMove3D = function(event) {
    var canvasCoordinates = getCanvasCoordinates(event);
    viewportCoordinates = this.convertCanvasToViewport(canvasCoordinates);
    var solarCoordinates4D = this.convertViewportToView(viewportCoordinates);
    var solarCoordinates3D = solarCoordinates4D.dup();
    solarCoordinates3D.elements.pop();
    var lastSolarCoordinates3D = this.lastSolarCoordinates4D.dup();
    lastSolarCoordinates3D.elements.pop();
    var mm = this.createRotationMatrixFromVectors(solarCoordinates3D, lastSolarCoordinates3D);
    if (mm != null) {
        this.mouseMatrix = this.mouseMatrix.x(mm);
        this.lastSolarCoordinates4D = solarCoordinates4D;
    }
}

viewportDetail.prototype.handleMouseMove2D = function(event) {
    var canvasCoordinates = getCanvasCoordinates(event);
    var viewportCoordinates = this.convertCanvasToViewport(canvasCoordinates);
    var planeCoordinates = this.convertViewportToPlane(viewportCoordinates);

    var mm = this.createTranslationMatrixFromVectors(planeCoordinates, this.lastPlaneCoordinates);
    if (mm != null) {
        this.translationMatrix = this.translationMatrix.x(mm);
        this.lastPlaneCoordinates = planeCoordinates;
    }
}

viewport = function() {
    this.gui
    this.totalWidth = 512;
    this.totalHeight = 512;
    this.rows = 1;
    this.columns = 1;
    this.maxRows = 4;
    this.maxColumns = 4;
    this.maxNumberOfViewPorts = this.maxRows * this.maxColumns;
    this.listeners = [];
    this.modeList = [ '2D', '3D', 'limb', 'limb-conformal' ]
    this.viewportDetails = [];
    for (var i = 0; i < this.maxNumberOfViewPorts; i++) {
        this.viewportDetails.push(new viewportDetail(0, 0, this.totalWidth, this.totalHeight, '2D', i));
    }
    this.updateGui();
};

viewport.prototype.getIndex = function(canvasCoord) {
    var x = canvasCoord.elements[0];
    var y = canvasCoord.elements[1];

    var numviews = this.columns * this.rows;
    var index = -1;
    var i = 0;
    while (i < numviews && index === -1) {
        vd = this.viewportDetails[i];
        if (vd.top <= y && y <= vd.top + vd.height && vd.left <= x && x <= vd.left + vd.width) {
            index = i;
        }
        i++;
    }
    return index;
}

viewport.prototype.setRows = function(rows) {
    this.rows = parseInt(rows);
}

viewport.prototype.setColumns = function(columns) {
    this.columns = parseInt(columns);
}

viewport.prototype.initGui = function() {
    this.viewportElement = document.getElementById("viewport");
    this.addRowElements();
    this.addWidthHeightControl();
}

viewport.prototype.addRowElements = function() {
    var toAdd = [ 'rowNumber', 'columnNumber' ];
    var labels = [ 'Rows:', 'Columns:' ]

    for (var i = 0; i < 2; i++) {
        var elLabel = document.createElement("label");
        elLabel.innerHTML = labels[i];

        var el = document.createElement("input");
        el.setAttribute("type", "number");
        el.setAttribute("data-type", toAdd[i]);
        el.setAttribute("min", 1);
        el.setAttribute("max", 4);
        el.setAttribute("step", 1);
        el.value = 1;
        el.addEventListener("change", this, false);
        elLabel.setAttribute("class", "alignlabel");
        el.setAttribute("class", "aligninput");
        this.viewportElement.appendChild(elLabel);
        this.viewportElement.appendChild(el);
        this.viewportElement.appendChild(document.createElement("br"));

    }
}

viewport.prototype.addWidthHeightControl = function() {
    var toAdd = [ 'width', 'height' ];
    var labels = [ 'Width:', 'Height:' ]
    for (var i = 0; i < 2; i++) {
        var elLabel = document.createElement("label");
        elLabel.innerHTML = labels[i];
        var el = document.createElement("input");
        el.setAttribute("type", "number");
        el.setAttribute("data-type", toAdd[i]);
        el.setAttribute("min", 128);
        el.setAttribute("max", 4096);
        el.setAttribute("step", 1);
        el.value = 512;
        el.addEventListener("change", this, false);
        elLabel.setAttribute("class", "alignlabel");
        el.setAttribute("class", "aligninput");
        this.viewportElement.appendChild(elLabel);
        this.viewportElement.appendChild(el);
        this.viewportElement.appendChild(document.createElement("br"));
    }
}

viewport.prototype.handleEvent = function(e) {
    switch (e.type) {
        case "change":
            var element = e.target || e.srcElement;
            var elementType = element.attributes["data-type"].value;
            if (elementType == "rowNumber") {
                this.setRows(element.value);
                this.viewportChanged();
            } else if (elementType == "columnNumber") {
                this.setColumns(element.value);
                this.viewportChanged();
            } else if (elementType == "width") {
                this.setWidth(element.value);
            } else if (elementType == "height") {
                this.setHeight(element.value);
            } else if (elementType == "comboViewportModes") {
                var elementViewport = parseInt(element.attributes["data-viewport"].value);
                var selectedIndex = element.selectedIndex;
                this.viewportDetails[elementViewport].mode = this.modeList[selectedIndex];
            }
    }
}

viewport.prototype.setHeight = function(height) {
    var canvas = document.getElementById("glcanvas");
    canvas.height = height;
    this.totalHeight = height;

}
viewport.prototype.setWidth = function(width) {
    var canvas = document.getElementById("glcanvas");
    canvas.width = width;
    this.totalWidth = width;
}

viewport.prototype.addListener = function(newlistener) {
    this.listeners.push(newlistener);
}

viewport.prototype.viewportChanged = function() {
    for (var i = 0; i < this.listeners.length; i++) {
        this.listeners[i].fireViewportChanged(this);
    }
    this.updateGui();
}

viewport.prototype.updateGui = function() {
    var viewportDiv = document.getElementById("viewportDiv");
    var w = this.totalWidth / this.columns;
    var h = this.totalHeight / this.rows;
    var index = 0;
    for (var rr = 0; rr < this.rows; rr++) {
        for (var ll = 0; ll < this.columns; ll++) {
            if (this.viewportDetails[index].mode === undefined) {
                this.loadViewportModesGui(viewportDiv, index);
                this.viewportDetails[ll].index = index;
            }
            this.viewportDetails[index].left = ll * w;
            this.viewportDetails[index].top = rr * h;
            this.viewportDetails[index].width = w;
            this.viewportDetails[index].height = h;
            index++;
        }
    }
    for (var index = this.columns * this.rows; index < this.maxNumberOfViewPorts; index++) {
        var el = document.getElementById("comboViewportModeDiv" + index);
        if (el !== null) {
            el.parentNode.removeChild(el);
            this.viewportDetails[index].mode = undefined;
        }
    }
}

viewport.prototype.loadViewportModesGui = function(viewportDiv, number) {
    var comboViewportModeDiv = document.createElement("div");
    comboViewportModeDiv.setAttribute("id", "comboViewportModeDiv" + number);
    viewportDiv.appendChild(comboViewportModeDiv);

    var comboViewportModes = document.createElement("select");
    comboViewportModes.setAttribute("class", "comboViewportModemap");
    comboViewportModes.setAttribute("multiple", "multiple");

    for (var i = 0; i < this.modeList.length; i++) {
        var comboViewportOption = document.createElement("option");
        comboViewportOption.setAttribute("value", i);
        comboViewportOption.innerHTML = this.modeList[i];
        comboViewportModes.appendChild(comboViewportOption);
        if (i === 0) {
            comboViewportOption.setAttribute("selected", true);
        }
    }
    var comboViewportLabel = document.createElement("label");
    comboViewportLabel.innerHTML = "Viewport " + number + " mode:";
    comboViewportModeDiv.appendChild(comboViewportLabel);
    comboViewportModeDiv.appendChild(comboViewportModes);
    comboViewportModeDiv.appendChild(document.createElement("br"));
    comboViewportModes.setAttribute("data-type", "comboViewportModes");
    comboViewportModes.setAttribute("data-viewport", "" + number);
    comboViewportModes.addEventListener("change", this, false);
    this.viewportDetails[number].mode = this.modeList[0];
}
