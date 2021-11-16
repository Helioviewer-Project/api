_core = function() {
    this.canvas;
    this.objectList = [];
    this.plottingMetadata = [];
    this.cubeRotation = 0.0;
    this.lastCubeUpdateTime = 0;
    this.mvMatrix;
    this.shaderProgram;
    this.vertexPositionAttribute;
    this.textureCoordAttribute;
    this.perspectiveMatrix;
    this.beginDate = 0;
    this.endDate = 0;
    this.currentDate = 0;
    this.cadence = 25 * 60 * 1000;
    this.running = false;
    this.loop = true;
    this.viewport;
    this.count = 0;
    this.bt = Date.now();
    this.elapsed;
    this.mvMatrixStack = [];
    this.gl = null;
    this.L0click = 0.;
    this.B0click = 0.;
    this.stepForward = false;
    this.stepBackward = false;

}

var core = new _core();

core.initWebGL = function() {
    try {
        core.gl = core.canvas.getContext("experimental-webgl");
    } catch (e) {
        alert(e);
    }
    if (!core.gl) {
        alert("Unable to initialize WebGL. Your browser may not support it.");
    }
}
var fff = 0;

core.start = function() {
    core.canvas = document.getElementById("glcanvas");
    core.initWebGL(core.canvas);
    if (core.gl) {
        core.gl.clearColor(0.0, 0.0, 0.0, 1.0);
        core.gl.clearDepth(1.0);
        core.gl.enable(core.gl.DEPTH_TEST);
        core.gl.depthFunc(core.gl.LEQUAL);
        setInterval(function() {
            requestAnimationFrame(core.drawScene)
        }, 30);
    }
}

core.drawScene = function() {
    core.gl.clear(core.gl.COLOR_BUFFER_BIT | core.gl.DEPTH_BUFFER_BIT);

    var vcl = core.viewport.totalWidth / core.viewport.columns;
    var vrl = core.viewport.totalHeight / core.viewport.rows;
    for (var ll = 0; ll < core.viewport.columns; ll++) {
        for (var rr = 0; rr < core.viewport.rows; rr++) {
            var index = core.viewport.columns * (core.viewport.rows - 1 - rr) + ll;
            // core.perspectiveMatrix = makePerspective(90 * core.zoom[index],
            // 1024.0 / 1024.0, 0.1, 100.0);
            // core.multMatrix(core.viewMatrix[index]);
            // core.mvRotate(fff, [ 1.0, 0.0, 0.0 ]);
            var mode = core.viewport.viewportDetails[index].mode;
            var curdate = new Date(core.currentDate);
            var viewDetail = core.viewport.viewportDetails[index]
            viewDetail.viewMatrix = viewDetail.getViewMatrix(mode, curdate, index);
            viewDetail.viewProjectionMatrix = viewDetail.projectionMatrix.x(viewDetail.viewMatrix);

            core.gl.viewport(ll * vcl, rr * vrl, vcl, vrl);

            for (var i = 0; i < core.objectList.length; i++) {
                var object = core.objectList[i];
                if (!object.initialized) {
                    object.init(core.gl);
                }
            }

            for (var i = 0; i < core.objectList.length; i++) {
                var object = core.objectList[i];
                object.prerender(core.gl);
            }

            for (var i = core.objectList.length - 1; i >= 0; i--) {
                var object = core.objectList[i];
                if (object.viewportIndices.indexOf(index) !== -1 && object.supportedModes.indexOf(mode) !== -1) {
                    object.render(core.gl, viewDetail.viewProjectionMatrix, core.currentDate, index);
                }
            }

            for (var i = 0; i < core.objectList.length; i++) {
                var object = core.objectList[i];
                object.updateGUI();
            }
            core.elapsed = Date.now() - core.bt;
            core.bt = Date.now();

        }
    }
    if (core.running || core.stepForward) {
        core.currentDate += core.cadence;
        core.stepForward = false;
    }
    if (core.stepBackward) {
        core.currentDate -= core.cadence;
        core.stepBackward = false;
    }
    if (core.currentDate < core.beginDate) {
        core.currentDate = core.beginDate;
    }
    if (core.currentDate > core.endDate && core.loop) {
        core.currentDate = core.beginDate;
    }
    var indicatorElement = document.getElementById("videoIndicator");
    indicatorElement.style.left = indicatorElement.parentNode.clientWidth * (core.currentDate - core.beginDate) / (core.endDate - core.beginDate);
    indicatorElement.setAttribute("data-tips", formatDate(new Date(core.currentDate)));
}

core.loadIdentity = function() {
    core.mvMatrix = Matrix.I(4);
}

core.multMatrix = function(m) {
    core.mvMatrix = core.mvMatrix.x(m);
}

core.mvTranslate = function(v) {
    this.multMatrix(Matrix.Translation($V([ v[0], v[1], v[2] ])).ensure4x4());
}

core.mvPushMatrix = function(m) {
    if (m) {
        core.mvMatrixStack.push(m.dup());
        core.mvMatrix = m.dup();
    } else {
        core.mvMatrixStack.push(core.mvMatrix.dup());
    }
}

core.mvPopMatrix = function() {
    if (!core.mvMatrixStack.length) {
        throw ("Can't pop from an empty matrix stack.");
    }

    core.mvMatrix = core.mvMatrixStack.pop();
    return core.mvMatrix;
}

core.mvRotate = function(angle, v) {
    var inRadians = angle * Math.PI / 180.0;

    var m = Matrix.Rotation(inRadians, $V([ v[0], v[1], v[2] ])).ensure4x4();
    this.multMatrix(m);
}

document.addEventListener("DOMContentLoaded", function(event) {
    base_url = "https://api.helioviewer.org/";
    var success = function(data) {
        var vgui = new gui();
        vgui.initGui(data);
        var vviewport = new viewport();
        vviewport.initGui();
        core.viewport = vviewport;
        core.gui = vgui;

        core.start();
        core.canvas.onmousedown = handleMouseDown;
        document.onmouseup = handleMouseUp;
        document.onmousemove = handleMouseMove;
        core.canvas.onmousewheel = handleMouseWheel;
        core.objectList.push(new sunPoints());
        core.gui.handleLoad();

    };
    getJSON(base_url + "?action=getDataSources&verbose=true&enable=[STEREO_A,STEREO_B,PROBA2]", success, success);
});
