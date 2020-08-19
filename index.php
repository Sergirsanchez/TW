<?php
  require_once "./vendor/autoload.php";
  include("db.php");

  $loader = new \Twig\Loader\FilesystemLoader('TW');
  $twig = new \Twig\Environment($loader);
  $correctLogin = 0;
  $wrongAccess = 0;
  $endSession = 0;
  $userData = null;

  /*Antes de nada, comprobamos si es la primera vez que entramos, para crear automáticamente toda la DB*/
  $isEmpty = dataBaseEmpty();

  if($isEmpty == 1){  /*DB vacía*/
    restoreDB("backup/originalDB.txt"); /*Escirbimos la base de datos*/


    /*Encontramos un problema, y este es que si al borrar y restaurar la BD, la receta contenida en la cookie no está , al entrar al index se nos devolverá una  página de receta
    pero vacía, así que para evitar eso, si vamos a construir la base de datos, eliminamos la cookie en el caso de que ya exista*/
    if(isset($_COOKIE['nameOfRecipe'])){
      setcookie("nameOfRecipe",'0',time()-1000);
    }
  }

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




if(empty($_COOKIE['nameOfRecipe'])){   /*Si la cookie está vacía (será en el caso de que no la tengamos)*/
    $recipeNames = getNames("mostrar todos");/*Obtenemos el nombre de todas las recetas*/

    /*Escogemos un random de la lista de recetas*/
    $randomRecipe = $recipeNames[rand(0,count($recipeNames)-1)];

    $recipe = createRecipe($randomRecipe);

    setcookie("nameOfRecipe",$recipe['nombre']);/*Establecemos la cookie*/

}

else{   /*Ya tenemos la cookie, por lo que ya habiamos entrado previamente a la página*/
    $recipe = createRecipe($_COOKIE['nameOfRecipe']);
}


$totalRecipes = getTotalRecipes();
$mostCommented = getMostCommented();


/*Función para crear el objeto $recipe*/
function createRecipe($nameOfRecipe){

    $recipe = getData($nameOfRecipe);

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

    return $recipe;
}

echo $twig->render('receta.html', ['totalRecipes'=>$totalRecipes,'recipe'=>$recipe, 'login'=>$login,'userData'=>$userData, 'loginError'=>$loginError,'mostCommented'=>$mostCommented]);


?>