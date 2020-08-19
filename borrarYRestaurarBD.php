<?php

  require_once "./vendor/autoload.php";
  include("db.php");

  $loader = new \Twig\Loader\FilesystemLoader('TW');
  $twig = new \Twig\Environment($loader);

    /*------------------------------------------------------ CONTROL DE LOGIN -----------------------------------------------------------*/
  $login = 0; /*Variable que nos indica si el usuario está logeado o no*/
  $endSession = 0;
  $dbDeleted = 0;
  $fileUploaded = 0;
  $fileError = "";

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

  /*--------------------------------- OBTENEMOS EL TOTAL DE LAS RECETAS -----------------------------------------*/
  $totalRecipes = getTotalRecipes();
  $mostCommented = getMostCommented();

  if($userData['tipo'] != "admin" ){
      header("Location:portada.php");
  }

  /*Recibimos variables por método GET*/

  if(isset($_GET['deleteDB'])){ /*Vamos a eliminar todos los datos de la base de datos*/
      $action = "deleteDB";
  }

  if(isset($_GET['restoreDB'])){    /*Vamos a restaurar la base de datos*/
      $action = "restoreDB";
  }



  /*Recibimos variables por método POST (de los propios formularios)*/
  if(isset($_POST['restoreDB'])){
    $action = "restoreDB";
  }

  if(isset($_POST['deleteBDOK'])){  /*Procedemos a borrar la base de datos*/
    deleteDB($_POST['adminID']);
    $dbDeleted = 1;

  }

  if($dbDeleted == 1){
      header("Location:portada.php?dbDeleted=1");
  }

  /*Nos llega el fichero, vamos a ver si está en el formato correcto*/

  if(isset($_FILES['uploadDB'])){
    $fileError = ""; /*Almacenaremos si hemos tenido error*/
    $fileTmpName = $_FILES['uploadDB']['tmp_name'];
    $fileName = $_FILES['uploadDB']['name'];

    $fileExtension = strtolower(end(explode(".", $fileName)));  /*Obtenemos la extensión*/


    if($fileExtension != "txt"){   /*Solo permitiremos esta extensión*/
        $fileError = "Extensión no permitida. Solo se permiten ficheros con extensión txt";
    }

    if($fileError == ""){   /*Todo ha ido OK*/
        move_uploaded_file($fileTmpName,"uploadedFiles/".$fileName);
        restoreDB("uploadedFiles/".$fileName);
        $fileUploaded = 1;
    }
  }


  if($fileUploaded == 1){
      header("Location:portada.php?fileUploaded=1");
  }

  echo $twig->render('borrarYRestaurarBD.html', ['fileError'=>$fileError,'action'=>$action,'totalRecipes'=>$totalRecipes,'login'=>$login,'userData'=>$userData,'mostCommented'=>$mostCommented]);

?>