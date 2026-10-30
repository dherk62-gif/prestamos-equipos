// Pide confirmación antes de enviar cualquier formulario con data-confirm.
document.addEventListener('submit', function (evento) {
  var mensaje = evento.target.getAttribute('data-confirm');
  if (mensaje && !window.confirm(mensaje)) {
    evento.preventDefault();
  }
});
