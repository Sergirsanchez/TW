<?php
  require_once "./vendor/autoload.php";
  include("db.php");

  $loader = new \Twig\Loader\FilesystemLoader('TW');
  $twig = new \Twig\Environment($loader);

    /*------------------------------------------------------ CONTROL DE LOGIN -----------------------------------------------------------*/
  $login = 0; /*Variable que nos indica si el usuario está logeado o no*/
  $endSession = 0;

  session_start();  /*Iniciamos sesión*/

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

  if(isset($_SESSION['user']) && isset($_SESSION['password'])){
    $login = 1;
    $userData = getUserData($_SESSION['user']);
  }


  if($endSession == 1){  /*Acabamos de cerrar la sesión*/
    $message = "El ".$userData['tipo']." ".$userData['nombre']." ".$userData['apellidos']." acaba de salir del sistema";
    insertIntoLog($message);
    $userData = null;
    
  }

   /*Obtenemos los datos de log*/
   $logData = getLogData();

  /*--------------------------------- OBTENEMOS EL TOTAL DE LAS RECETAS -----------------------------------------*/
  $totalRecipes = getTotalRecipes();
  $mostCommented = getMostCommented();

  if($userData['tipo'] != "admin"){
      header("Location:portada.php");
  }



  echo $twig->render('log.html', ['logData'=>$logData,'totalRecipes'=>$totalRecipes,'login'=>$login,'userData'=>$userData,'mostCommented'=>$mostCommented]);


?>