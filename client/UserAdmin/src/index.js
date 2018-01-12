import React from 'react'
import ReactDOM from 'react-dom'
import App from './App'
import client from './cache/apollo-client/index'
import { ApolloProvider } from 'react-apollo'
import './index.css'

ReactDOM.render(
  <ApolloProvider client={client} >
    <App />
  </ApolloProvider>, document.getElementById('wp-graphql-jwt-user-admin')
);
