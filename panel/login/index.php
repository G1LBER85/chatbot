<?php

session_start();

if(isset($_SESSION['usuario'])){
    header("location: dashboard.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Y Registro - MagtimusPro</title>

    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        *{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            text-decoration: none;
            font-family: 'Roboto', sans-serif;
        }

        body{
            background-image: url(imagen/imagenfondo.png);
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center;
            background-attachment: fixed;
        }

        main{
            width: 100%;
            padding: 20px;
            margin: auto;
            margin-top: 100px;
        }

        .contenedor__todo{
            width: 100%;
            max-width: 800px;
            margin: auto;
            position: relative;
        }

        .caja__trasera{
            width: 100%;
            padding: 10px 20px;
            display: flex;
            justify-content: center;
            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);
            background-color: rgba(0, 128, 255, 0.5);
            border-radius: 20px;
        }

        .caja__trasera div{
            margin: 100px 40px;
            color: white;
            transition: all 500ms;
        }

        .caja__trasera-register{
            text-align: right;
            margin-left: auto;
        }

        .caja__trasera div p,
        .caja__trasera button{
            margin-top: 30px;
        }

        .caja__trasera div h3{
            font-weight: 400;
            font-size: 26px;
        }

        .caja__trasera div p{
            font-size: 16px;
            font-weight: 300;
        }

        .caja__trasera button{
            padding: 10px 40px;
            border: 2px solid #fff;
            font-size: 14px;
            background: transparent;
            font-weight: 600;
            cursor: pointer;
            color: white;
            outline: none;
            transition: all 300ms;
        }

        .caja__trasera button:hover{
            background: #fff;
            color: #46A2FD;
        }

        .contenedor__login-recuperacion{
            display: flex;
            align-items: center;
            width: 100%;
            max-width: 380px;
            position: relative;
            top: -185px;
            left: 10px;
            z-index: 10;
            transition: left 500ms cubic-bezier(0.175, 0.885, 0.320, 1.275);
        }

        .contenedor__login-recuperacion form{
            width: 100%;
            padding: 80px 20px;
            background: white;
            position: absolute;
            border-radius: 20px;
        }

        .contenedor__login-recuperacion form h2{
            font-size: 30px;
            text-align: center;
            margin-bottom: 20px;
            color: #46A2FD;
        }

        .contenedor__login-recuperacion form input{
            width: 100%;
            margin-top: 20px;
            padding: 10px;
            border: none;
            background: #F2F2F2;
            font-size: 16px;
            outline: none;
        }

        .contenedor__login-recuperacion form button{
            padding: 10px 40px;
            margin-top: 40px;
            border: none;
            font-size: 14px;
            background: #46A2FD;
            font-weight: 600;
            cursor: pointer;
            color: white;
            outline: none;
            border-radius: 5px;
        }

        .formulario__login{
            opacity: 1;
            display: block;
        }

        .formulario__recuperacion{
            display: none;
        }

        @media screen and (max-width: 850px){
            main{
                margin-top: 50px;
            }

            .caja__trasera{
                max-width: 350px;
                height: 300px;
                flex-direction: column;
                margin: auto;
            }

            .caja__trasera div{
                margin: 0px;
                position: absolute;
            }

            .caja__trasera-register{
                 width: 320px;
                 text-align: right;
                 margin-left: auto;
            }           

            .contenedor__login-recuperacion{
                  top: -185px;
                  left: 10px;
                  margin: auto;
            }

            .contenedor__login-recuperacion form{
                  position: absolute;
            }
        }
    </style>
</head>
<body>

    <main>
        <div class="contenedor__todo">
            <div class="caja__trasera">
                <div class="caja__trasera-login">
                    <h3>PREPARATORIA NO. 3</h3>
                    <p>Inicia sesión para entrar a admin</p>
                    <button type="button" id="btn__iniciar-sesion">Iniciar Sesión</button>
                </div>
                <div class="caja__trasera-register">
                    <h3>¿Olvidaste tu contraseña?</h3>
                    <p>Escribe el correo con el que te registraste y te mandamos un enlace.</p>
                    <button type="button" id="btn__recuperacion">Enviar correo</button>
                </div>
            </div>

            <div class="contenedor__login-recuperacion">
                <!-- Formulario Login -->
                <form action="/chatbot/panel/login/php/inicio/login_usuario_be.php" method="POST" class="formulario__login">
                    <h2>Iniciar Sesión</h2>
                    <input type="text" placeholder="Usuario" name="usuario" required>
                    <input type="password" placeholder="Contraseña" name="contrasena" required>
                    <button type="submit">Entrar</button>
                </form>

                <!-- Formulario Recuperación -->
                <form action="/chatbot/panel/login/php/recuperacion/enviar_recuperacion.php" method="POST" class="formulario__recuperacion">
                    <h2>Recuperar cuenta</h2>
                    <input type="email" placeholder="Correo Electrónico" name="correo_recuperacion" required>
                    <button type="submit">Enviar correo</button>
                </form>
            </div>
        </div>
    </main>

    <script>
        document.getElementById("btn__iniciar-sesion").addEventListener("click", iniciarSesion);
        document.getElementById("btn__recuperacion").addEventListener("click", recuperarCuenta);
        window.addEventListener("resize", anchoPage);

        var formulario_login = document.querySelector(".formulario__login");
        var formulario_recuperacion = document.querySelector(".formulario__recuperacion");
        var contenedor_login_recuperacion = document.querySelector(".contenedor__login-recuperacion");
        var caja_trasera_login = document.querySelector(".caja__trasera-login");
        var caja_trasera_register = document.querySelector(".caja__trasera-register");

        function anchoPage(){
            if (window.innerWidth > 850){
                caja_trasera_register.style.display = "block";
                caja_trasera_login.style.display = "block";
            }else{
                caja_trasera_register.style.display = "block";
                caja_trasera_register.style.opacity = "1";
                caja_trasera_login.style.display = "none";
                formulario_login.style.display = "block";
                contenedor_login_recuperacion.style.left = "0px";
                formulario_recuperacion.style.display = "none";   
            }
        }

        anchoPage();

        function iniciarSesion(){
            if (window.innerWidth > 850){
                formulario_login.style.display = "block";
                contenedor_login_recuperacion.style.left = "10px";
                formulario_recuperacion.style.display = "none";
                caja_trasera_register.style.opacity = "1";
                caja_trasera_login.style.opacity = "0";
            }else{
                formulario_login.style.display = "block";
                contenedor_login_recuperacion.style.left = "0px";
                formulario_recuperacion.style.display = "none";
                caja_trasera_register.style.display = "block";
                caja_trasera_login.style.display = "none";
            }
        }

        function recuperarCuenta(){
            if (window.innerWidth > 850){
                formulario_recuperacion.style.display = "block";
                contenedor_login_recuperacion.style.left = "410px";
                formulario_login.style.display = "none";
                caja_trasera_register.style.opacity = "0";
                caja_trasera_login.style.opacity = "1";
            }else{
                formulario_recuperacion.style.display = "block";
                contenedor_login_recuperacion.style.left = "0px";
                formulario_login.style.display = "none";
                caja_trasera_register.style.display = "none";
                caja_trasera_login.style.display = "block";
                caja_trasera_login.style.opacity = "1";
            }
        }
    </script>

    <!-- PROCESAMIENTO DE NOTIFICACIONES SWEETALERT2 -->
    <?php if (isset($_GET['status'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const status = "<?= htmlspecialchars($_GET['status']); ?>";

                if (status === 'enviado') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Correo enviado!',
                        text: 'Si el correo está registrado, recibirás un enlace de recuperación.',
                        confirmButtonColor: '#46A2FD'
                    });
                } else if (status === 'error') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de envío',
                        text: 'Ocurrió un problema al enviar el correo. Por favor intentalo más tarde.',
                        confirmButtonColor: '#d33'
                    });
                } else if (status === 'login_requerido') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Acceso restringido',
                        text: 'Por favor, debes iniciar sesión para acceder a esta sección.',
                        confirmButtonColor: '#46A2FD'
                    });
                }

                // Limpia la URL para evitar re-mostrar la alerta al recargar
                window.history.replaceState({}, document.title, window.location.pathname);
            });
        </script>
    <?php endif; ?>

</body>
</html>