<!-- Banner flotante oculto (display: none) -->
<div style="display: none;">
    <span>La sesión expira en:</span>
    <strong id="number">1:00</strong>
</div>

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
            n = 60; // Reinicia el tiempo
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
                alert("La sesión ha expirado por inactividad.");
                window.location.href = "login/php/inicio/cerrar_sesion.php";
            }
        }, 1000);
    })();
</script>
