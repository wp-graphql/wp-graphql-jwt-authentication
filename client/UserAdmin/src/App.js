import React, { Component } from 'react'
import {
  Card,
  Input,
  Icon
} from 'antd';
import { graphql } from 'react-apollo'
import gql from 'graphql-tag'

/**
 * Setup the App
 */
class App extends Component {
  render() {


    if ( this.props && this.props.data ) {
      const data = this.props.data;

      if ( data.loading ) {

        return (<div>Loading...</div>)

      } else if ( data.user ) {

        console.log( data.user );

        return (
          <div style={{
            padding: '10px'
          }}>
            <Card title="WPGraphQL JWT Auth User Settings">
              <Input disabled addonAfter={<Icon type="key"/>} defaultValue={data.user.name}/>
            </Card>
          </div>
        );

      } else if ( data.errors ) {
        return (<div>Error!</div>)
      }

    }

  }
}

const USER_JWT_SETTINGS = gql`
  query getUserJwtSettings( $userId: ID! ) {
      user( id: $userId ) {
          name
      }
  }
`;

const ConnectedApp = graphql(USER_JWT_SETTINGS, {
  options: {
    variables: {
      userId: "dXNlcjox"
    }
  }
})(App);

export default ConnectedApp;