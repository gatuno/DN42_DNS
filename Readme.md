# Installing

## Dependencies

You will need the following things:

* PHP 7
* A web server (apache, nginx, etc...)
* A rabbitMQ server
* PhpAmqpLib lib installed. If your installation is not standard, adjust the 'autoload_files' in the config file.
* Bind9 server

## Installing for Debian

Run the following to install the web dependencies:

`apt-get install apache2 libapach2-mod-php php-amqplib php-mbstring php-mysql`

On the server who is going to be the rabbitMQ Server, run:

`apt-get install rabbitmq-server`

On the server who is going to be BIND9 DNS Server, run:

`apt-get install bind9 php-amqplib php-cli php-mbstring php-mysql`

## Prepare RabbitMQ Server

On the Rabbit MQ Server, prepare a vhost and a username for use with the web and background service. Create a rabbitmq user:

`rabbitmqctl add_user dns_messages`

You will be prompted to input a password. Next, create a vhost for the messages:

`rabbitmqctl add_vhost dns_vhost`

Next, grant permissions to the created user on the created vhost:

`rabbitmqctl set_permissions -p "dns_vhost" "dns_messages" ".*" ".*" ".*"`

Recommended, delete the guest user on rabbitmq (unless you are using on other things)

`rabbitmqctl delete_user guest`

## Prepare bind9 Server

On the Bind9 Server, you will need to allow dynamic new zones, and add two keys, one for the RNDC control, another one for updates.

Edit /etc/bind/named.conf.options, inside the options block, add following lines: 

```
allow-new-zones yes;
serial-update-method date;
```

Next, generate a control key:

`tsig-keygen -a HMAC-MD5 machine_control_key`

And generate a dynamic update key. This key is only needed on master bind server, you can omit it on the slaves.

`tsig-keygen -a HMAC-SHA256 update_key_a`

On the bind.conf.options, aftere the options block, put the following lines (please copy both keys from the output of the previous command):

```
key "machine_control_key" {
        algorithm hmac-md5;
        secret "==A_SECRET==";
};

key "update_key_a" {
        algorithm hmac-sha256;
        secret "==A_SECRET==";
};

controls {
        inet 127.0.0.1 port 953
        allow { 127.0.0.1; ::1; }
        keys {
                "rndc-key"; // This is the default bind key, don't remove it
                "machine_control_key";
        };
};
```

Create the folder `/etc/bind/dynamic_zones` and change their permissions:

```
mkdir /etc/bind/dynamic_zones
chown bind:bind /etc/bind/dynamic_zones
chmod 770 /etc/bind/dynamic_zones
```

Also, disable apparmor.

## Configure WEB

Copy the souce code, to some folder in your server, let's say `/home/www/dns`, next, you need to fill the base config file. Copy the config file:

```
cd /homw/www/dns
cp src/DN42/conf/dns42.php.dist src/DN42/conf/dns42.php
```

Next, fill some important fields.

* Be sure to set the `$cfg['debug']` to false in production environments.
* If the 'debug' variable is set to false, fill the admins array to recieve email notifications on errors.
* Fill the 'secret_key' with a good random string (at least 40 chars).
* Configure the url section. Otherwise, the URL will not be generated correctly.
* Connect and configure a database engine. Please use MySQL, PostgreSQL is maybe broken.
* Configure the RabbitMQ section, with the values the user, vhost, password and server 
* Configure `rndc_name` put the FQDN of this master Bind9 server. Yes, a FQDN is needed.
* If you are going to use Bind9 slaves, add them on the `rndc_slaves`. Use FQDNs. If not using slaves, empty the array.
* Configure the `$cfg['current_default_update_key']` correctly with the name of the key generated above (`update_key_a`)

## Bootstrapping the database

To bootstrap the database, use the script `migrate.php` like this:

```
cd /homw/www/dns/src
php migrate.php --conf=DNS42/conf/dns42.php -a -d -i`
```

Next, modify the script `bootstrap_first_user.php` and fill your data, and run it `php bootstrap_first_user.php` to create the first admin user.

Next, modify the script `bootstrap_first_update_key.php` with the key generated "update_key_a", and

## Configure processing messages on BIND9 server

Copy the souce code, to some folder in your BIND9 server, let's say `/home/www/dns`. If your WEB and BIND9 server are the same, you can use the same folder for both things. Copy the config file (skip if done already):

```
cd /homw/www/dns
cp src/DN42/conf/dns42.php.dist src/DN42/conf/dns42.php
```

Next, fill some important fields.

* Debug, URL section and secret_key can be skipped if BIND9 is a different server (otherwise, they are already configured)
* Connect and configure a database engine. Please use MySQL, PostgreSQL is maybe broken. Database section is needed on both servers.
* Configure the RabbitMQ section, with the values the user, vhost, password and server. RabbitMQ section is needed on both servers.
* Configure the rndc_* variables
	* If this server is going to be the master configure the following defaults:
		* `rndc_type` must be "master"
		* `rndc_name` put the FQDN of this server. Yes, a FQDN is needed.
		* `rndc_master` put the FQDN and IP of this server.
	* If you are going to have BIND slaves, add them in the `rndc_slaves` config. Slave names need FQDNs and IP pairs. Otherwise, empty the array
	* If this server is going to be a slave, fill the following defaults:
		* `rndc_type` must be "slave"
		* `rndc_name` put the name of this server. On slaves a FQDN is needed too.
		* `rndc_master` must be filled with the MASTER name and IP.
		* `rndc_slaves` can be empty on slaves
	* For both master and slaves, create a file named `machine_control.key` on the folder /home/www/dns/src/DN42/conf, and put the following content (generated from the above section):

```
key "machine_control_key" {
        algorithm hmac-md5;
        secret "==A_SECRET==";
};
```

Run the command:

`php process_dns.php`

If everything goes fine, install it as a service.

## Enable the web server

This is an example configuration for the web server using apache virtualhosts:

```
<VirtualHost *:80>
	ServerName dns.example.com
	
	ServerAdmin root@somewhere.com
	DocumentRoot /home/www/dns/www/
	
	<Directory /home/www/dns>
		AllowOverride All
		Require all granted
		Options -Indexes
	</Directory>
	
	ErrorLog ${APACHE_LOG_DIR}/error.log
	CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

# Bugs

It is a known bug that if you change the variable `rndc_slaves` on the config file, the old zones created will not be updated, so the new slaves will not receive notifications.
