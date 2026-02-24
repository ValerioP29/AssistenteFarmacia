/* global bootstrap: false */
(() => {
  'use strict'
  const tooltipTriggerList = Array.from(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  tooltipTriggerList.forEach(tooltipTriggerEl => {
    new bootstrap.Tooltip(tooltipTriggerEl)
  })
})

// Gestione logout nel sidebar
document.addEventListener('DOMContentLoaded', () => {
  const logoutLinks = document.querySelectorAll('.logout-link');

  logoutLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      
      if (typeof logout === 'function') {
        logout(); // Usa la funzione logout definita nel footer (che già include la conferma)
      } else {
        // Fallback se la funzione logout non è disponibile
        if (confirm('Vuoi davvero uscire?')) {
          window.location.href = 'logout.php';
        }
      }
    });
  });
});