import { ApolloClient } from 'apollo-client';
import { HttpLink } from 'apollo-link-http';
import { InMemoryCache } from 'apollo-cache-inmemory';

/**
 * Instantiate a new instance of ApolloClient
 * @type {ApolloClient}
 */
const client = new ApolloClient({
  link: new HttpLink({
    uri: 'http://wpgraphql.test/graphql'
  }),
  cache: new InMemoryCache(),
});

/**
 * Export the ApolloClient as the default export
 */
export default client;