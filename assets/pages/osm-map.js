document.getElementById('carte-osm-load').addEventListener('click', function () {
  var box = document.getElementById('carte-osm');
  var frame = document.createElement('iframe');
  frame.src = box.dataset.src;
  frame.title = 'Localisation ES Coutances — Stade Paul-Maundrell';
  box.replaceChildren(frame);
});

