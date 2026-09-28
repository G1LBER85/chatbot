<!-- Banner flotante oculto -->
<div style="display: none;">
    <span>La sesión expira en:</span>
    <strong id="number">1:00</strong>
</div>

<!-- Incluir SweetAlert2 si no se ha cargado previamente en el archivo principal -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Script de conteo e inactividad -->
<script type="text/javascript">
    (function() {
        let n = 60; // 60 segundos
        const l = document.getElementById("number");

        function formatearTiempo(segundos) {
            const minutos = Math.floor(segundos / 60);
            const segRestantes = segundos % 60;
            const minFormatted = minutos < 10 ? "0" + minutos : minutos;
            const segFormatted = segRestantes < 10 ? "0" + segRestantes : segRestantes;
            return minFormatted + ":" + segFormatted;
        }

        function reiniciarContador() {
            n = 60; // Reinicia el tiempo al detectar movimiento/teclado
            if (l) l.innerText = formatearTiempo(n);
        }

        // Detecta actividad del usuario
        document.onmousemove = reiniciarContador;
        document.onkeydown = reiniciarContador;

        if (l) l.innerText = formatearTiempo(n);

        const intervalId = window.setInterval(function() {
            n--;
            if (l) l.innerText = formatearTiempo(n);

            if (n <= 0) {
                clearInterval(intervalId);

                // Muestra el modal gráfico de sesión expirada
                Swal.fire({
                    icon: 'warning',
                    title: 'Sesión Expirada',
                    text: 'Tu sesión ha finalizado por inactividad. Por favor, vuelve a iniciar sesión.',
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: '#46A2FD',
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = "login/php/inicio/cerrar_sesion.php?status=expirado";
                    }
                });

                // Redirección automática tras 5 segundos si el usuario no presiona "Aceptar"
                setTimeout(() => {
                    window.location.href = "login/php/inicio/cerrar_sesion.php?status=expirado";
                }, 5000);
            }
        }, 1000);
    })();
</script>