A module to export and import the email templates from magento 2

## Installation
	composer require template-provider/email-templates-import-export

### How to export EMail Templates

bin/magento tp:export:email-templates
 
 Export Directory is :var/export/

### How to import EMail Templates
 
bin/magento tp:import:email-templates update update emailtemplates_page.zip
 
mode or media-mode : update -> for override existing template

mode or media-mode : skip -> for Skip




