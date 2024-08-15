<?php

namespace Controllers;

use Classes\Email;
use Model\Usuario;
use MVC\Router;

class LoginController {


    public static function login(Router $router) {

        $alertas = [];

        if($_SERVER ['REQUEST_METHOD'] === 'POST') {
            // echo 'Desde POST';

            $auth = new Usuario($_POST);

            $alertas = $auth->validarLogin();

            if(empty($alertas)) {
                // echo 'El usuario proporcionó correo y contraseña';

                //Comprobar que exista el usuario
                $usuario = Usuario::buscarPorCampo('email', $auth->email);
                
                if($usuario) {

                    if($usuario->comprobarContrasenaAndVerificado($auth->password)) {
                        //Autenticar Usuario
                        session_start();

                        $_SESSION['id'] = $usuario->id;
                        $_SESSION['nombre'] = $usuario->nombre . ' ' . $usuario->apellido;
                        $_SESSION['email'] = $usuario->email;
                        $_SESSION['login'] = true;

                        if($usuario->admin == 1) {
                            $_SESSION['admin'] = $usuario->admin ?? null;
                            header('Location: /admin');
                        } else {
                            header('Location: /cliente');
                        }

                        // debuguear($_SESSION);
                    }

                } else {
                    Usuario::setAlerta('error', 'Usuario no encontrado');
                }
            }


        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/login',[
            'alertas' => $alertas
        ]);

    }

    public static function logout() {
        echo 'Desde logout';
    }
    public static function olvide(Router $router) {
        
        $alertas = [];

        if($_SERVER ['REQUEST_METHOD'] === 'POST') {
            $auth = new Usuario($_POST);
            $alertas = $auth->validarEmail();
            if(empty($alertas)) {
                $usuario = Usuario::buscarPorCampo('email', $auth->email);
                
                if($usuario && $usuario->confirmado == 1) {

                    $usuario->crearToken();
                    $usuario->guardar();

                    $email = new Email(
                        $usuario->email,
                        $usuario->nombre,
                        $usuario->token
                    );

                    $email->enviarInstrucciones();
                   
                    //TODO: Enviar email
                    Usuario::setAlerta('exito', 'Revisa tu email');
                    
                } else {
                    Usuario::setAlerta('error', 'El usuario no existe o no esta confirmado');
                    
                }
            }
        }
        
        $alertas = Usuario::getAlertas();
        $router->render('auth/olvide-password', [
            'alertas' => $alertas
        ]);
    }
    public static function recuperar(Router $router) {

        $alertas = [];

        $error = false;

        $token = s($_GET['token']);

        // \debuguear($token);

        //Buscar usuario por token
        $usuario = Usuario::buscarPorCampo('token', $token);

        if(empty($usuario)) {
            Usuario::setAlerta('exito', 'Token no valido');
            $error = true;
        }

        if($_SERVER ['REQUEST_METHOD'] === 'POST') {
            $password = new Usuario($_POST);
            $alertas = $password->validarPassword();

            if(empty($alertas)) {
                $usuario->password = null;

                $usuario->password = $password->password;
                $usuario->hashPassword();
                $usuario->token = null;

                $resultado = $usuario->guardar();
                if($resultado) {
                    header('Location: / ');
                }
                // debuguear($usuario);
            }
            // \debuguear($password);
        }


        $alertas = Usuario::getAlertas();
        

        // \debuguear($usuario);
        // echo 'Desde recuperar';
        $router->render('auth/recuperar-password', [
            'alertas' => $alertas,
            'error' => $error
        ]);
    }

    public static function crear(Router $router) {

        $usuario = new Usuario;
        
        //Alertas vacias
        $alertas = [];

        if($_SERVER ['REQUEST_METHOD'] === 'POST') {

            $usuario->sincronizar($_POST);
            $alertas = $usuario->validarNuevaCuenta();

            //Revisar que las alertas esten en vacio
            if(empty($alertas)) {
                //Verificar que el usuario no este registrado o no exista
                $resultado = $usuario->existeUsuario();

                //debuguear($usuario);

                if ($resultado->num_rows) {
                    $alertas = Usuario::getAlertas();
                } else {
                    //Hasear el password
                    $usuario->hashPassword();
                    
                    //Gernerar un token único
                    $usuario->crearToken();

                    //Enviar el email
                    $email = new Email($usuario->email, $usuario->nombre, $usuario->token);
                    $email->enviarConfirmacion();

                    //Crear el usuario.
                    $resultado = $usuario->guardar();
        
                    //debuguear($usuario);

                    if($resultado) {
                        header('Location: /mensaje');
                    }
                }
            }
        }


        $router->render('auth/crear-cuenta',[
            'usuario' => $usuario,
            'alertas' => $alertas
        ]);
        
    }

    public static function confirmar(Router $router) {

        $alertas = [];

        $token = s($_GET['token']);

        //debuguear($token);

        $usuario = Usuario::buscarPorCampo('token', $token);

        if(empty($usuario)) {
           // echo 'Token no valido';
           Usuario::setAlerta('error', 'Token no válido');
        } else {
            //echo 'Token válido, confirmando usuario...';
            $usuario->confirmado = 1;
            $usuario->token = '';

            // debuguear($usuario);

            $usuario->guardar();
            Usuario::setAlerta('exito', 'Cuenta comprobada correctamente');
        }


        $alertas = Usuario::getAlertas();
        $router->render('auth/confirmar-cuenta', [
            'alertas' => $alertas
        ]);
        
    }
    
    public static function mensaje(Router $router) {
        
        $router->render('auth/mensaje');
    }

    public static function admin() {
        echo 'Desde admin';
    }

    public static function cliente() {
        echo 'Desde cliente';
    }
    
}