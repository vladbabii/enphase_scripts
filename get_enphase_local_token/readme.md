# Get Entrez Enphase Token

Automatically get token from https://entrez.enphaseenergy.com/ to use with local API

! Important !
This is a work in progress proof of concept. It works but it can be better.

What you need
* your enphase email and password for your account
* your site id can be found on enlighten.enphaseenergy.com/ at the bottom of the page (near the change link/button)
* your meter/gateway serial number can be found  on enlighten.enphaseenergy.com by going to devices - here you should have your gateways/meters (and other devices)

What the script does
* connects to https://entrez.enphaseenergy.com/
* logs in if necesarry
* if needed it generates a new token for the chosen site id + meter/gateway serial number
* checks the token is valid
* writes the token to "token.txt" file 
* returns the token as output

The script can be used as
* command line script with "php enphase.php"
* cron job to generate token file
* api under a web server (apache, nginx, etc)
