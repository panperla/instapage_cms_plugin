--- Instapage CMS Plugin v3 fixed --- 

This module has been developed by (https://instapage.com) and downloaded from https://help.instapage.com/hc/en-us/articles/207038297-Publishing-Your-Page-to-Drupal. Unfortunately it is not working well with profile based Drupal installations due to hard coded paths and assumption that module will always be in /sites/all/modules which is incorrect. This fix simply remove hardcoded paths and use drupal_get_path function.

--- Original Instapage README ---

Module: Instapage CMS Plugin
Author: Instapage


Description
===========
The best way for Drupal to seamlessly publish landing pages as a natural extension of your website.


Requirements
============
Instapage user account that you can get on <https://app.instapage.com>.


Installation
============
To install this module, place it in your sites/all/modules folder and enable it
on the modules page.


Usage
=====
First connect your page to your Instapage account. You can do this on 
settings tab of plugin's dashboard at /admin/structure/instapage_cms_plugin

You will have to enter username and password of your Instapage account
or use your Instapage Token.

After you succesfully connect, you can see all your pages from Instapage
(if you used Instapage credentials as a login method) or pages from subaccount 
bound with tokens you used. 

Keep in mind that the pages will only show up if you publish them for Drupal
on Instapage administration pages.

After you get a list of the pages you can put in the path for each of them and publish
them as a regular page, home page or 404 page.
