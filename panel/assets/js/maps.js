document.getElementById('open_map').addEventListener('click', function () {
  document.getElementById('map_modal').style.display = 'flex';
  setTimeout(() => map.invalidateSize(), 200); // correzione layout Leaflet
});

document.getElementById('close_map').addEventListener('click', function () {
  document.getElementById('map_modal').style.display = 'none';
});

// Inizializza mappa
const map = L.map('map').setView([45.4642, 9.1900], 10); // Milano come centro

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

let marker;

map.on('click', function (e) {
  const lat = e.latlng.lat.toFixed(6);
  const lng = e.latlng.lng.toFixed(6);

  if (marker) marker.setLatLng(e.latlng);
  else marker = L.marker(e.latlng).addTo(map);

  document.getElementById('lat_long').value = `${lat}, ${lng}`;
});
