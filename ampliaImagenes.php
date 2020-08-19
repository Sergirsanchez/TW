<?php
      require_once "./vendor/autoload.php";
      include("db.php");
    
    
      $loader = new \Twig\Loader\FilesystemLoader('TW');
      $twig = new \Twig\Environment($loader);

      /*------------------------------------------------------ CONTROL DE LOGIN -----------------------------------------------------------*/
 $login = 0; /*Variable que nos indica si el usuario está logeado o no*/
 $filename = $_SERVER['PHP_SELF']; /*Indicamos el nombre del fichero actual, de forma que cuando nos logueemos nos reenvíe a la misma página*/
 $usernameError = "";  /*Para indicar el error si hemos introducido el usuario o la contraseña mal*/
 $passwordError = "";
 $loginError = [];  /*Para controlar errores en el logueo, devolviendo mensajes*/

 session_start();  /*Iniciamos sesión*/

 if($_SERVER["REQUEST_METHOD"] == "POST"){
   if(isset($_POST['login'])){ /*Si hemos pulsado en el botón de logueo*/
    
    if(!empty($_POST['user'])){  /*Hemos introducido un nombre de usuario*/
      if(!empty($_POST['password'])){  /*Y hemos introducido una contraseña*/
        if(checkLogin($_POST['user'],$_POST['password']) == 1){  /*Datos correctos; introducimos los datos en las variables de sesión*/ 
          $_SESSION['user'] = $_POST['user'];
          $_SESSION['password'] = $_POST['password'];

          $userData = getUserData($_SESSION['user']);
          $_SESSION['userID'] = $userData['id'];

          $correctLogin = 1;
        }

        else{ /*Hemos introducido usuario y contraseña, pero alguno de los datos ha fallado*/
          if(userExists($_POST['user'],null) == 1){  /*Si el usuario existe, el problema es la contraseña*/
            $passwordError = "Contraseña incorrecta";
            $wrongAccess = 1;
          }

          else{ /*Error en el nombre de usuario*/
            $usernameError = "Usuario incorrecto";
          }

        }
      }

      else{ /*No hemos introducido contraseña*/
        $passwordError = "Introduce una contraseña";

      }

    }

    else{ /*No hemos introducido usuario*/
      $usernameError = "Introduce un usuario";
    }

    $loginError['user'] = $usernameError;
    $loginError['password'] = $passwordError;

   }

   if(isset($_POST['unlogin'])){/*Si hemos pulsado el botón de desloguear*/
     if(session_status()==PHP_SESSION_NONE){
         session_start();
     }

     /*Obtenemos los datos del usuario conectado*/
     $userData = getUserDataByID($_SESSION['userID']);
     $endSession = 1;  
     
     session_unset();
     $cookieParams= session_get_cookie_params(); 
     
     setcookie(session_name(), $_COOKIE[session_name()], time()-2592000, $cookieParams['path'], 
     $cookieParams['domain'], $cookieParams['secure'], $cookieParams['httponly']);
     
     session_destroy();
   } 
 }

 if(isset($_SESSION['user']) && isset($_SESSION['password'])){
   $login = 1;
   $userData = getUserData($_SESSION['user']);
   
 }

 if($correctLogin == 1){  /*Acabamos de entrar al sistema*/
  $message = "El ".$userData['tipo']." ".$userData['nombre']." ".$userData['apellidos']." acaba de acceder al sistema";
  insertIntoLog($message);
}

if($endSession == 1){  /*Acabamos de cerrar la sesión*/
  $message = "El ".$userData['tipo']." ".$userData['nombre']." ".$userData['apellidos']." acaba de salir del sistema";
  insertIntoLog($message);
  $userData = null;
}

if($wrongAccess == 1){  /*Intento de acceso erróneo al sistema*/
  $message = "El usuario ".$_POST['user']." ha intenado acceder erróneamente al sistema";
  insertIntoLog($message);
}

$totalRecipes = getTotalRecipes();
$mostCommented = getMostCommented();

if(isset($_GET['makePhotoBigger'])){
  $photo = $_GET['makePhotoBigger'];
  $recipeName = $_GET['recipeName'];
}

else{ /*Nop recibimos esa variable, reenviamos a portada*/
  header("Location:portada.php");
}



echo $twig->render('ampliaImagenes.html', ['photo'=>$photo,'recipeName'=>$recipeName,'totalRecipes'=>$totalRecipes,'login'=>$login,'loginError'=>$loginError,'userData'=>$userData,'mostCommented'=>$mostCommented]);
?>