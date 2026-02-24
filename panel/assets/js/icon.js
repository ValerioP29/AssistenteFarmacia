document.addEventListener('DOMContentLoaded', function() {


    fetch('..\farmacia_file\icon.html')
    .then(response => response.text())
    .then(data => {
        document.getElementById('contenuto').innerHTML = data;
    });

});