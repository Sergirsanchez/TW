<?php
  require_once "./vendor/autoload.php";
  include("db.php");

  
  $loader = new \Twig\Loader\FilesystemLoader('TW');
  $twig = new \Twig\Environment($loader);

  $sent = 0;
  $correctLogin = 0;
  $endSession = 0;
  $wrongAccess = 0;
  $userData = null;

  if(isset($_GET['sent'])){
    $contact['sent'] = 1;
  }

  /*Obtener la información acerca de los comentarios ya publicados*/
  $comments = getFAQComments();
  
  $contact['comments'] = $comments;

  /*Validar los datos del formulario*/
  $form = []; /*Va a contener todos los datos*/
  $checked = false;
  $sent = 0; /*Nos va a indicar si el formulario está o no para enviar, para mostrar el alert correspondiente al envío correcto de datos*/

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
    $message = "El usuario ".$userData['nombre']." ".$userData['apellidos']." acaba de acceder al sistema";
    insertIntoLog($message);
  }

  if($endSession == 1){  /*Acabamos de cerrar la sesión*/
    $message = "El usuario ".$userData['nombre']." ".$userData['apellidos']." acaba de salir del sistema";
    insertIntoLog($message);
    $userData = null;

  }

  if($wrongAccess == 1){  /*Intento de acceso erróneo al sistema*/
    $message = "El usuario ".$_POST['user']." ha intenado acceder erróneamente al sistema";
    insertIntoLog($message);
  }


/*Recibimos variables por POST*/
 if($_SERVER["REQUEST_METHOD"] == "POST"){
  if(isset($_POST['OK'])){

    if($_POST['OK'] == 0){    /* Aún hay que validar*/

      $email = $phone = $comment = "";
      $emailError = $phoneError = $commentError = "";


      if(empty($_POST["email"])){
        $emailError = "El correo es obligatorio";
      }
  
      else if (checkFormatEmail($_POST["email"]) == false){
        $emailError = "Introduce un correo válido";
      }
      else{
        $email = test_input($_POST["email"]);
      }
  
  
      if(empty($_POST["phone"])){
        $phone = "";
      }
      else{
        $phone = test_input($_POST["phone"]);
  
        if (checkFormatPhone($phone) == false){
          $phoneError = "Introduce un teléfono válido";
        }
  
      }
     
  
      if(empty($_POST["comment"])){
        $commentError = "El comentario es obligatorio";
      }
      else{
        $comment = test_input($_POST["comment"]);
      }
    
      $form = ['email'=>$email,'emailError'=>$emailError,'phone'=>$phone,'phoneError'=>$phoneError,'comment'=>$comment,'commentError'=>$commentError];
  
      $contact['form'] = $form;

  
      if($emailError == "" & $phoneError == "" & $commentError == ""){ /*Si no hemos tenido ningún error*/
        $checked = true;
        $contact['checked'] = $checked;
      }
    }

    else{ /*Formulario enviado a validar*/
      $sent = 1;
      createComment($_POST['authorName'],$_POST['authorSurname'],null,$_POST['commentText']);
    }
  }
  

}

if($login == 1){  /*Si el usuario está logueado, ponemos directamente su nombre, si no, ponemos "Anónimo"*/
  $userName = $_SESSION['user'];
}

else{
  $userName = "Anónimo";
}

$contact['form']['authorName'] = $userName; /*Asignamos el nombre*/
$contact['form']['authorSurname'] = $userData['apellidos']; /*Asignamos los apellidos del autor del comentario*/

if($login == 1){
  $contact['form']['email'] = $userData['email'];
}



  /*Función para validar la entrada de los formularios, evitando posibles ataques*/
  function test_input($data) {
    $data = trim($data);  /*Eliminamos espacios extras que sobren*/
    $data = stripslashes($data);  /*Eliminamos los "\" */
    $data = htmlspecialchars($data); /*Evitamos que se introduzcan caracteres especiales, traduciendolos a texto plano*/
    return $data;
  }

  /*Función para ver que el correo cumple con el formato necesario*/
  function checkFormatEmail($email){
    $correct = false;

    if(preg_match('/^[A-z0-9\\._-]+@[A-z0-9][A-z0-9-]*(\\.[A-z0-9_-]+)*\\.([A-z]{2,6})$/', $email)){
      $correct = true;
    }

    return $correct;
  }

  /*Función para ver que el teléfono cumple con el formato necesario*/
  function checkFormatPhone($phone){
    $correct = false;
    
    if(preg_match('/^(\(\+[0-9]{2}\))?\s*[0-9]{3}\s*[0-9]{6}$/', $phone)){
      $correct = true;
    }
    
    return $correct;
  }

    /*Añadir el total de las recetas*/
    $totalRecipes = getTotalRecipes();
    $mostCommented = getMostCommented();


    
  if($sent == 1){ /*En esta if se reenvía a sí mismo, para que si refrescamos la página cuando sale el alert no vuelva a introducir el mensaje en la DB*/
    Header("Location:contacto.php?sent=1");
  }

  echo $twig->render('contacto.html', ['contact'=>$contact,'totalRecipes'=>$totalRecipes,'login'=>$login,'filename'=>$filename,'loginError'=>$loginError,'userData'=>$userData,
                      'mostCommented'=>$mostCommented]);

?>