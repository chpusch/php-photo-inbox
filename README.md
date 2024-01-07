1. Within the index.php (line 2) change the "CHANGE THIS SECRET FOLDER NAME" to a random folder name (upload folder)
2. Generate the folder on the ftp server
3. For additional "security" generate a .htaccess to disalow folder access from the web to the upload folder.
4. Generate the .htpasswd within the mac termainal with following command: htpasswd -c htpasswd USERNAME. Afterwards move it to a non public path on your server. This fie must be referenced within the .htaccess.
