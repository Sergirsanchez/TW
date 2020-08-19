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
 $correctLogin = 0;
 $endSession = 0;
 $wrongAccess = 0;
 $userData = null;
 $commentCreated = 0;
 $deletedComment = 0;

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



  if(isset($_POST['closeMenu'])){/*Hemos pulsado en mandar comentario*/
    if(isset($_POST['commentTextArea'])){
      $auxComment = trim($_POST['commentTextArea']); /*para que no haya un comentario que sea solo espacios*/

      if(!empty($auxComment)){  /*comentario no nulo*/
        $auxComment = test_input($auxComment);  /*Validamos la cadena*/

        if($login == 0){  /*Usuario no logeado*/
          createComment("Anónimo",null,$_POST['recipeName'],$auxComment);
        }

        else{
          createComment($userData['nombre'],$userData['apellidos'],$_POST['recipeName'],$auxComment);
          
        }
        $commentCreated = 1;
      }
    }

  }
    

  if(isset($_GET['deletedComment'])){ /*Nos indica si hemos llegado a la página tras borrar un comentario*/
    $deletedComment = $_GET['deletedComment'];
  }

  if(isset($_POST['sendRating'])){  /*Acabamos de enviar una nota sobre esa receta*/
    addRate($_POST['recipeID'],$_POST['userID'],$_POST['rateOfRecipe']); /*Añadimos la nota a la receta*/
    header("Location:receta.php?name=".$_POST['name']); /*Reenviamos a la misma página para que si el usuario la refresca no se vuelva a introducir el voto en la BD*/
  }
    
  
  if(isset($_GET['name'])){
    /*Vamos a comprobar si el nombre se corresponde con el de una receta, de forma que si se escribe en la URL el nombre de una receta que no existe,
    va a redirigir a la receta de rissoto de calabaza y champiñones*/

    $isValid = validRecipe($_GET['name']);  

    if($isValid == 1){
      $name = $_GET['name'];
    }

    else{
      Header("Location:portada.php?notFound=1");
    }

    $recipe = getData($name); /*Obtenemos la info de la receta a partir del nombre*/
    $rated = recipeRated($userData['id'],$recipe['id']); /*Comprobamos si el usuario ya ha puntuado o no la receta*/

    
  }

  if(isset($_POST['name'])){  /*Este caso es en el que el nombre de la receta llega desde el formulario de login, lo cual significa que nos hemos deslogueado o hemos hecho un login erroneo*/
    $isValid = validRecipe($_POST['name']);  

    if($isValid == true){
      $name = $_POST['name'];
    }

    $recipe = getData($name); /*Obtenemos la info de la receta a partir del nombre*/
    $rated = recipeRated($userData['id'],$recipe['id']); /*Comprobamos si el usuario ya ha puntuado o no la receta*/

    
  }

  if(!isset($_GET['name']) & !isset($_POST['name'])){ /*Hemos escrito en la URL solo "receta.php", u otras variables que no tenemos en cuenta*/
    header("Location: portada.php");
  }


  $auxIngredients = explode(",",$recipe['ingredientes']);/*Almacenamos los ingredientes ya separados, para volver a pasarselos a la variable "ingredientes" de recipe*/
    
  for($i = 0, $size = count($auxIngredients); $i < $size; ++$i){  /*Si tienen algún espacio a la derecha, lo borramos*/
    $auxIngredients[$i] = trim($auxIngredients[$i]);
  }

  $recipe['ingredientes'] = $auxIngredients;

  /*Ponemos las etiquetas en el formato que necesitamos*/
  $auxTags = $recipe['etiquetas'][0];

  for($i = 1, $size = count($recipe['etiquetas']); $i < $size; ++$i){
    $auxTags = $auxTags.",".$recipe['etiquetas'][$i];
  }

  $recipe['etiquetas'] = $auxTags;

  /*función para validar que no intentan hacernos ningún ataque*/
  function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
  }
   
  $totalRecipes = getTotalRecipes();
  $mostCommented = getMostCommented();

  setcookie("nameOfRecipe",$recipe['nombre']);/*Establecemos la cookie*/

  if($commentCreated == 1){ /*Evitamos que tras crear un comentario, si refrescamos la página, se vuelva a crear e introducir en la BD*/
    header("Location:receta.php?name=".$_POST['recipeName']);
  }

  echo $twig->render('receta.html', ['recipe'=>$recipe,'totalRecipes'=>$totalRecipes,'login'=>$login,'filename'=>$filename,'loginError'=>$loginError,'userData'=>$userData,
                                    'deletedComment'=>$deletedComment, 'mostCommented'=>$mostCommented,'rated'=>$rated]);

?>