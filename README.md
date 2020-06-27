# Mailchimp sign-up form in Drupal8/9

This is a simple Drupal 8/9 module that provides an ajax sign-up form to register new users to a Mailchimp audience list. 

The module also provides a configuration form to store the Maichimp credentials and other sign-up form configurations.

Install
-------
- Download the module in your /modules/custom directory.
- Install it with Drupal Console: ```drupal moi kb_mailchimp```
- Install it with Drush: drush en ```drush kb_mailchimp``

Use
---
To add your Maichimp credentials (API Key and List ID), go to:
```/admin/kb_mailchimp/credentials/config```

To add a new user to your mailchimp list, go to:
```kb_mailchimp/signup```

Tree
------
```
|-- config
|   |-- install
|       |-- mailchimp_credentials.config.yml
|-- src
    |-- Form
    |   |-- MailchimpCredentialsConfigForm.php
    |   |-- MailchimpSignForm.php
    |-- Service
        |-- MailchimpService.php
|-- kb_mailchimp.info.yml
|-- kb_mailchimp.links.menu.yml
|-- kb_mailchimp.module
|-- kb_mailchimp.routing.yml
|-- kb_mailchimp.services.yml
```


Please read [my blog](http://karimboudjema.com/) or [get in touch](http://karimboudjema.com/en/contact).





