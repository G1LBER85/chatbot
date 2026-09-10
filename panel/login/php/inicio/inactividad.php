<!-- Banner flotante posicionado a la derecha sin afectar la maquetación -->
<div style="
    position: fixed;
    top: 15px;
    right: 140px;
    z-index: 9999;
    background-color: #f8d7da;
    color: #842029;
    border: 1px solid #f5c2c7;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 14px;
    font-family: sans-serif;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 5px;
">
    <span>La sesión expira en:</span>
    <strong id="number" style="color: #dc3545; font-size: 15px; font-weight: bold;">15:00</strong>
</div>

<!-- Script de conteo e inactividad -->
<script type="text/javascript">
    (function() {
        let n = 900; // 900 segundos = 15 minutos
        const l = document.getElementById("number");

        // Función para convertir segundos a formato MM:SS
        function formatearTiempo(segundos) {
            const minutos = Math.floor(segundos / 60);
            const segRestantes = segundos % 60;
            
            // Agrega un 0 a la izquierda si el número es menor a 10 (ej: "09" en lugar de "9")
            const minFormatted = minutos < 10 ? "0" + minutos : minutos;
            const segFormatted = segRestantes < 10 ? "0" + segRestantes : segRestantes;

            return minFormatted + ":" + segFormatted;
        }

        function reiniciarContador() {
            n = 900; // Reinicia a 15 minutos
            if (l) l.innerText = formatearTiempo(n);
        }

        // Detecta actividad del usuario
        document.onmousemove = reiniciarContador;
        document.onkeydown = reiniciarContador;

        // Establecer el formato inicial al cargar la página
        if (l) l.innerText = formatearTiempo(n);

        const intervalId = window.setInterval(function() {
            n--;
            if (l) l.innerText = formatearTiempo(n);

            if (n <= 0) {
                clearInterval(intervalId);
                alert("La sesión ha expirado por inactividad.");
                // Cambiar en la redirección por inactividad:
                window.location.href = "login/php/inicio/cerrar_sesion.php";
            }
        }, 1000);
    })();
</script>