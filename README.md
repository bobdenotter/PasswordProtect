PasswordProtect extension
=========================

The "PasswordProtect extension" is a small extension that allows you to
password protect one or more of your pages with a password. Use it by simply
placing the following in your template:

```twig
    {{ passwordprotect() }}
```

You can put this either in your template, to protect an entire contenttype, or
just place it in the body text of a record.

People who do not yet have access will automatically be redirected to a page,
where they will be asked to provide the password.

See `config.yml` for the options.

**Note:** This 'protection' should not be considered 'secure'. The password
will be sent over the internet in plain text, so it can be intercepted if
people use it on a public WiFi network, or something like that.

In order to get this extension to work. You need to add allowtwig: true to the
field were you want to insert the twigcode.

The 'password' page
-------------------

The page you've set as the `redirect:` option in the `config.yml` can be any
Bolt page you want. It can be a normal page, where you briefly describe why the
visitor was suddenly redirected here. And, perhaps you can give instructions on
how to acquire the password, if they don't have it. When the user provides the
correct password, they will automatically be redirected back to the page they
came from.

To insert the actual form with the password field, simply use:

```twig
    {{ passwordform() }}
```

Like above, you can do this either in the template, or simply in the content of
the page somewhere.

**Tip:** do not be a dumbass and require a login on the page you're redirecting
to! Your visitors will get stuck in an endless loop, if you do.

After logging on
----------------

If you're on a page, and you'd like to display to a visitor that they're logged
on, you can use the following:

```twig
{% if passwordprotect_username() %} %}
    <p>Hello, {{ passwordprotect_username() }}</p>
{% endif %}
```

Generating password hashes
--------------------------

This extension comes with a small tool, to help you generate proper hashes. To
generate hashed passwords for your visitors, go to `/bolt/protect/generatepasswords`
to create password hashes. Note that you must be logged on to the Bolt backend,
to do so.

Restricting Access to Content Types
-----------------------------------

You can restrict access to content types by editing the `config.yml` and adding
the content types name next to `contentType`.

Modifying the public Form page
-----------------------------------

You can change the form being asked for in the `config.yml` by changing the
`form` parameter. An example of the form is under `templates/passwordform.twig`.

Changing passwords without modifying the YML file
-------------------------------------------------

To enable this feature, add this to your `config.yml`:

```yaml
allow_setting_password_in_backend: true
```

Passwords can be edited without modifying the YML file by going to the `Extras`
menu. There should be a link called `Edit Password`. It will edit the password
and show you the existing password if it is stored in plaintext.

Note: Using this feature will overwrite your `config.yml`. Be aware that
whitespace and comments in the file are lost.
