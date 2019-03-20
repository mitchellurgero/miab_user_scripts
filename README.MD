User Scripts for Mail-In-A-Box by Mitchell Urgero <info@urgero.org> (@murgero on discourse)


***BY RUNNING ANY SCRIPT IN THIS REPOSITORY YOU AGREE THAT YOU ARE LIABLE FOR ANY DAMAGES CAUSED. SCRIPT IS PROVIDED AS-IS. USE AT OWN RISK***

# User Copy

**This is a user migration tool for Mail-In-A-Box. It requires API access. 
This does not, I repeat, DOES NOT COPY EMAILS. Use imapsync for that:
https://github.com/imapsync/imapsync**

To use this script, you must edit the configuration below, then run

`php ./user-copy.php --host="box.example.com" --username="admin@example.com" --current-domain="example.net" --new-domain="example.org"`

in your console. Any computer with PHP7+ will work fine with this tool.
Once the tool starts, it will ask you to confirm before continuing. 

