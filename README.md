# WPGraphQL JWT Authentication

This plugin extends the <a href="https://github.com/wp-graphql/wp-graphql" target="_blank">WPGraphQL</a> plugin to provide authentication using JWT (JSON Web Tokens)

JSON Web Tokens are an open, industry standard [RFC 7519](https://tools.ietf.org/html/rfc7519) method for representing claims securely between two parties.

This plugin was initially based off the `wp-api-jwt-auth` plugin by Enrique Chavez (https://github.com/Tmeister), but modified specifically for use with the <a href="https://github.com/wp-graphql/wp-graphql" target="_blank">WPGraphQL</a> plugin.

## How it Works

This plugin adds a new `login` mutation to the WPGraphQL Schema. 

This can be used like so: 

```
mutation LoginUser {
  login( input: {
    login: "your_login"
    password: "your password"
  } ) {
    authToken
    user: {
      id
      name
    }
  }
}
```