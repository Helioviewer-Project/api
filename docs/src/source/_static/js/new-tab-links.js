// Open links inside literal blocks (e.g. .. parsed-literal:: example URLs) in a
// new tab. Scoped to ".literal-block a" so normal prose links are unaffected.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.literal-block a').forEach(function (link) {
    link.setAttribute('target', '_blank');
    link.setAttribute('rel', 'noopener noreferrer');
  });
});
