# WPGraphQL JWT Authentication

---

### NO LONGER SUPPORTED/RECOMMENDED: 
THIS PLUGIN IS LONGER RECOMMENDED OR SUPPORTED FOR AUTH WITH WPGRAPHQL. AUTH IS RECOMMENDED TO HAPPEN OUTSIDE OF GRAPHQL, NOT FROM WITHIN. SEE EXISTING AUTH SOLUTIONS FOR WORDPRESS, SUCH AS: 

* https://github.com/WP-API/OAuth1
* https://github.com/WP-API/basic-auth

NOTE: Even though these plugins are listed for use with the WP-API, they work fine with WPGraphQL, as the authorization mechanism happens before the GraphQL requests are executed, very much like how these plugins work with the WP REST API.

---

This plugin extends the <a href="https://github.com/wp-graphql/wp-graphql" target="_blank">WPGraphQL</a> plugin to provide authentication using JWT (JSON Web Tokens)

JSON Web Tokens are an open, industry standard [RFC 7519](https://tools.ietf.org/html/rfc7519) method for representing claims securely between two parties.

This plugin is based off the `wp-api-jwt-auth` plugin by Enrique Chavez (https://github.com/Tmeister), but modified specifically for use with the <a href="https://github.com/wp-graphql/wp-graphql" target="_blank">WPGraphQL</a> plugin.

### IN ACTION

![WPGraphQL JWT AUTH DEMO - LOGIN, RECEIVE TOKEN AND USER DATA](https://github.com/wp-graphql/wp-graphql-jwt-authentication/blob/master/wpgraphql-auth.gif?raw=true "LOGIN, RECEIVE TOKEN AND USER DATA")

### REQUIREMENTS

### WPGraphQL

This plugin exists as an extension of <a href="https://github.com/wp-graphql/wp-graphql" target="_blank">WPGraphQL</a> and requires that plugin to be active.

### PHP

**Minimum PHP version: 5.3**

### PHP HTTP Authorization Header enabled

Check with your hosting provider to determine if the Authorization headers are supported or how to add support if they aren't.

### CONFIGURATION & ACTIVATION
### Configure the Secret Key

The JWT needs a **secret key** to sign the token this **secret key** must be unique and never revealed.

To add the **secret key** edit your wp-config.php file and add a new constant called **GRAPHQL_JWT_AUTH_SECRET_KEY**

`
define('GRAPHQL_JWT_AUTH_SECRET_KEY', 'your-top-secrect-key');
`

You can use a string from here https://api.wordpress.org/secret-key/1.1/salt/

### Configurate CORs Support

The **wp-graphql-jwt-authentication** plugin has the option to activate [CORs](https://en.wikipedia.org/wiki/Cross-origin_resource_sharing) support.

To enable the CORs Support edit your wp-config.php file and add a new constant called **GRAPHQL_JWT_AUTH_CORS_ENABLE**

`
define('JWT_AUTH_CORS_ENABLE', true);
`

Finally activate the plugin within your wp-admin.
