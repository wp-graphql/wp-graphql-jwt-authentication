# About WPGraphQL JWT Auth
This plugin is an extension to the WPGraphQL WordPress plugin, providing a way to Authenticate using GraphQL mutations, 
retrieve JWT Auth and JWT Auth Refresh tokens which can then be used in the headers of future requests. 

Here's how it works: 
- Client sends GraphQL mutation to Login and asks for `jwtAuthToken` and `jwtRefreshToken` in response
- Client stores tokens (local storage, etc) for later use
- 
