<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Migrar Prestashop a XAMPP</title>
	<style type="text/css">
		body { font-family: sans-serif; }
	</style>
</head>
<body>

<?php

$host_name = "localhost";
$host_port = "";
$ddbb_host = "127.0.0.1";
$ddbb_user = "root";
$ddbb_pass = "";
$table_prefix = "ps_"; // se actualiza a partir del fichero de configuración de Prestashop

function update_config_file($folder, $ddbb_name) {

	global $ddbb_host;
	global $ddbb_user;
	global $ddbb_pass;

	// el archivo existe?
	$full_path = "c:\\xampp\\htdocs\\$folder\\app\\config\parameters.php";
	if (!file_exists($full_path)) {
		die("Archivo de configuración de Prestashop no encontrado: $full_path");
	}

	# lo cargamos
	$contenido = file_get_contents($full_path);

	# separamos por lineas
	$lineas = explode("\n", $contenido);

	# reescribimos algunas lineas de la configuracion
	for ($i=0; $i<count($lineas); $i++) {
		$linea = $lineas[$i];

		if (strpos($linea, "database_host") !== FALSE) {
			$lineas[$i] = "    'database_host' => '$ddbb_host', ";
		} elseif (strpos($linea, "database_port") !== FALSE) {
			$lineas[$i] = "    'database_port' => '', ";
		} elseif (strpos($linea, "database_name") !== FALSE) {
			$lineas[$i] = "    'database_name' => '$ddbb_name', ";
		} elseif (strpos($linea, "database_user") !== FALSE) {
			$lineas[$i] = "    'database_user' => '$ddbb_user', ";
		} elseif (strpos($linea, "database_password") !== FALSE) {
			$lineas[$i] = "    'database_password' => '$ddbb_pass', ";
		} elseif (strpos($linea, "database_prefix") !== FALSE) {
			// OBTENEMOS EL PREFIJO DE LAS TABLAS!
			global $table_prefix;
			$prefix = explode("=>", $linea)[1];
			$prefix = str_replace("'", "", $prefix);
			$prefix = str_replace(",", "", $prefix);
			$table_prefix = trim($prefix);
		}

		//print($linea."<br>");
	}

	# volvemos a rejuntar las lineas
	$contenido = implode("\n", $lineas);	

	# lo guardamos
	file_put_contents($full_path, $contenido);

}

function delete_dir(string $dirPath, bool $delete_folder): void {
    if (!is_dir($dirPath)) {
        throw new InvalidArgumentException("$dirPath debe ser un directorio");
    }

    $files = glob($dirPath . '/*', GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file)) {
            delete_dir($file, true); // Llamada recursiva para subcarpetas
        } else {
            unlink($file); // Eliminar archivo
        }
    }

    // una vez borrado todo, borramos la carpeta?
    if ($delete_folder) {
    	rmdir($dirPath);
    }
}

function delete_cache($folder) {

	$full_path = "c:\\xampp\\htdocs\\$folder\\var\\cache\\prod\\";
	delete_dir($full_path, false);

}

function get_full_host() {

	global $host_name;
	global $host_port;

	$fullhost = $host_name;
	if ($host_port != "") {
		$fullhost .= ":".$host_port;
	}

	return $fullhost;

}

function update_ddbb($ddbb_name, $folder) {

	global $ddbb_host;
	global $ddbb_user;
	global $ddbb_pass;
	global $table_prefix;	

	$host = get_full_host();

	// conectamos con el servidor
	$conex = mysqli_connect($ddbb_host, $ddbb_user, $ddbb_pass, $ddbb_name);
	if (!$conex) {
    	die("Error de conexión: " . mysqli_connect_error());
	}	

	// ps_shop_url
	$table_name = $table_prefix."shop_url";
	$sql = "UPDATE $table_name SET domain = '$host', domain_ssl = '$host', physical_uri = '/$folder/';";
	if (!mysqli_query($conex, $sql)) {
	    die("Error al actualizar : " . $table_name);
	}	

	// ps_configuration
	$table_name = $table_prefix."configuration";
	$sql = "UPDATE $table_name SET value = '$host' WHERE name LIKE 'PS_SHOP_DOMAIN%';";

	// cerramos la conexion
	mysqli_close($conex); 

}

function update_htaccess_file($folder) {

	// el archivo existe?
	$full_path = "c:\\xampp\\htdocs\\$folder\\.htaccess";
	if (!file_exists($full_path)) {
		die("Archivo .htaccess no encontrado: $full_path");
	}

	# lo cargamos
	$contenido = file_get_contents($full_path);

	# separamos por lineas
	$lineas = explode("\n", $contenido);

	# reescribimos algunas lineas
	for ($i=0; $i<count($lineas); $i++) {
		$linea = $lineas[$i];

		if (strpos($linea, "[E=REWRITEBASE:") !== FALSE) {
			$lineas[$i] = "RewriteRule . - [E=REWRITEBASE:/$folder/]";
		} elseif (strpos($linea, "ErrorDocument 404") !== FALSE) {
			$lineas[$i] = "ErrorDocument 404 /$folder/index.php?controller=404";
		} 

		//print($linea."<br>");
	}

	# volvemos a rejuntar las lineas
	$contenido = implode("\n", $lineas);	

	# lo guardamos
	file_put_contents($full_path, $contenido);

}

if ($_POST) {

	$host_name = trim($_POST['host']);	
	$host_port = trim($_POST['port']);	
	$ddbb_user = trim($_POST['dbuser']);	
	$ddbb_pass = trim($_POST['dbpass']);	

	$folder = trim($_POST['folder']);
	$ddbb_name = trim($_POST['ddbb']);

	echo "<h2>Proceso de migración</h2>";
	echo "<ul>";
	update_config_file($folder, $ddbb_name);	
	echo "<li>Archivo de configuración modificado.</li>";
	echo "<li>El prefijo de las tablas es: [$table_prefix].</li>";
	delete_cache($folder);
	echo "<li>Cache borrada.</li>";
	update_ddbb($ddbb_name, $folder);
	echo "<li>BD actualizada.</li>";
	update_htaccess_file($folder);
	echo "<li>.ht_access actualizado.</li>";
	echo "</ul>";

	echo "<p><strong>Proceso finalizado!</strong></p>";

	echo "<h2>Notas adicionales</h2>";
	echo "<p>Si desconoces las credenciales de acceso puedes hacer lo siguiente:</p>";
	echo "<ul>";
	echo "<li>En la tabla <strong>_employee</strong> de la base de datos tienes los usuarios, para hacer login debes de usar el email como usuario.</li>";
	echo '<li>Puedes modificar el fichero <strong>classes/Employee.php</strong>, concretamente la función <strong>getByEmail()</strong> para que no compruebe la contraseña asignandole FALSE a la variable <strong>$shouldCheckPassword</strong>. Obviamente, esto hará que cualquiera pueda acceder, asi que acuerdate de restaurar el codigo una vez hayas entrado y cambiado la contraseña.</li>';
	echo "</ul>";
	echo '<p>Una vez dentro del backend, acuerdate de ir a <strong>"Parametros de la tienda > Trafico & SEO"</strong> y desactivar y volver a activar las <strong>URL amigables</strong> (y despues darle a guardar). De lo contrario es posible que tengas problemas para ver las imagenes de productos y tal.</p>';
	
} else {

?>
	
	<h1>Migración Prestshop</h1>

	<p>Este es un script muy sencillo para migrar un Prestashop a XAMPP (local). Lo primero es descargar todos los archivos del servidor Web a una carpeta de nuestra instalación local de XAMPP (en htdocs), y hacer lo mismo con la base de datos.</p>

	<p>Después podemos utilizar esta herramienta para modificar los archivos y la base de datos para finalizar la migración.</p>

	<p>Esta herramienta solo ha sido probada en Windows 10 (como no modifica permisos, probablemente en otros sistemas fallará).</p>

	<p><strong style="color: red">IMPORTANTE: Este script borra carpetas, modifica ficheros, bases de datos... Cero garantias, usa bajo tu propia responsabilidad.</strong></p>

	<h2>Compatibilidad de Prestashop con PHP</h2>

	<p><strong style="color: red">IMPORTANTE</strong>: Antes que nada debes de comprobar que tu versión de PHP es compatible con al versión de Prestashop.</p>

<?php
	$version = phpversion();
	echo "<p><strong>Tu versión de PHP es: $version</strong></p>";
?>

	<p><strong>La versión de Prestashop</strong> se puede consultar en el archivo /app/AppKernel.php</p>

	<p>Aqui tienes las tablas de compatibilidad:</p>	

	<ul>
		<li>
			<a target="_blank" href="https://devdocs.prestashop-project.org/1.7/basics/installation/system-requirements/#php-compatibility-chart">
				Prestashop 1.7
			</a>
		</li>
		<li>
			<a target="_blank" href="https://devdocs.prestashop-project.org/8/basics/installation/system-requirements/#php-compatibility-chart">
				Prestashop 8
			</a>
		</li>		
	</ul>

	<h2>Configuración deseada</h2>

<?php
	$current_server_port = trim($_SERVER['SERVER_PORT']);
	if ($current_server_port == "80") {
		$current_server_port = "";
	}
?>	

	<form method="post">
		<ul>
			<li>
				<label for="host">Servidor:</label>
				<input type="text" id="host" name="host" value="localhost">
			</li>			
			<li>
				<label for="port">Puerto:</label>
				<input type="text" id="port" name="port" value="<?php echo $current_server_port; ?>">
				<small>(Para el puerto estandar 80 dejar vacio)</small>
			</li>
			<li>
				<label for="dbuser">Usuario de la Base de Datos:</label>
				<input type="text" id="dbuser" name="dbuser" value="root">
			</li>			
			<li>
				<label for="dbpass">Contraseña de la Base de Datos:</label>
				<input type="password" id="dbpass" name="dbpass" value="">
			</li>					
			<li>
				<label for="folder">Nombre de la carpeta:</label>
				<input type="text" id="folder" name="folder" value="prestashop">
				<small>(La carpeta dentro de C:\XAMPP\htdocs donde tenemos el Prestashop)</small>
			</li>
			<li>
				<label for="ddbb">Nombre de la bbdd:</label>
				<input type="text" id="ddbb" name="ddbb" value="prestashop">
				<small>(La base de datos donde tenemos el Prestashop)</small>
			</li>			
		</ul>
		<div class="form-buttons">
			<input type="submit" value="Iniciar proceso de migración">
		</div>
	</form>
<?php
}
?>

</body>
</html>