var mouseDown = false;

var activeIndex = 0;
getCanvasCoordinates = function(event) {
    var rect = core.canvas.getBoundingClientRect();
    var coordinates = $V([ event.clientX - rect.left, event.clientY - rect.top ]);
    return coordinates;
}

function handleMouseDown(event) {
    var canvasCoordinates = getCanvasCoordinates(event);
    activeIndex = core.viewport.getIndex(canvasCoordinates);
    var vpDetail = core.viewport.viewportDetails[activeIndex];
    vpDetail.handleMouseDown(event);
}

function handleMouseUp(event) {
    var vpDetail = core.viewport.viewportDetails[activeIndex];
    vpDetail.handleMouseUp(event);
}

function handleMouseMove(event) {
    var vpDetail = core.viewport.viewportDetails[activeIndex];
    vpDetail.handleMouseMove(event);
}

handleMouseWheel = function(event) {
    var canvasCoordinates = getCanvasCoordinates(event);
    var index = core.viewport.getIndex(canvasCoordinates);
    var vpDetail = core.viewport.viewportDetails[index];
    vpDetail.handleMouseWheel(event);
}