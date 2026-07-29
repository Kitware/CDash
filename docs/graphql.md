# GraphQL API

CDash exposes most of its data through a [GraphQL](https://graphql.org) API, allowing users to
build custom tooling, dashboards, or scripts on top of CDash.  This document describes the
conventions used by CDash's GraphQL API.  For a general introduction to GraphQL concepts, refer
to the official [tutorial](https://graphql.org/learn/).

## The GraphQL endpoint

CDash's GraphQL API is served from a single endpoint, `/graphql` (for example,
`https://my.cdash.org/graphql`).  Every query and mutation is submitted as an HTTP `POST` request
to this endpoint, with a JSON body containing a `query` field and, optionally, a `variables`
field.  These requests are typically constructed by GraphiQL or by client libraries such as the
Python `gql` library.

Accessing data associated with a protected or private project requires an authentication token.
Authentication is performed by generating an authentication token and including it in an
`Authorization` header.  A token can be generated using the `createAuthenticationToken` mutation,
or from the "Authentication Tokens" section of the user settings page (`/profile`).

```
Authorization: Bearer <your token>
```

## GraphiQL

CDash bundles [GraphiQL](https://github.com/graphql/graphiql), an in-browser tool for exploring
and testing the GraphQL API, hosted at `/graphql/explorer`.  GraphiQL supports writing and
executing queries against a CDash instance, with autocompletion, inline documentation, and syntax
highlighting, and is recommended for prototyping queries prior to implementation.

GraphiQL's "Docs" panel, accessible via the top left icon on the sidebar, provides a browsable
view of the entire schema, including every type, field, argument, and enum value along with its
description.

## Pagination

The CDash GraphQL API uses [Relay-style](https://relay.dev/graphql/connections.htm) pagination to
maintain efficient API calls.  Fields that return a list of records (such as `projects` or
`builds`) return a "connection" rather than a plain list.  A connection wraps its results in
`edges`, where each edge contains a `node` (the underlying record) and a `cursor` (an opaque
string identifying its position), along with a `pageInfo` object describing the current page:

```graphql
query {
  projects(first: 5) {
    pageInfo {
      hasNextPage
      endCursor
    }
    edges {
      node {
        id
        name
      }
    }
  }
}
```

By default, connections return 100 edges per request.  The `first` argument specifies the number
of edges to return.  Because large result sets may exceed the server's memory limit, pagination
is always recommended.  To retrieve the next page, pass the previous page's `endCursor` value as
the `after` argument:

```graphql
query {
  projects(first: 5, after: "<endCursor from the previous page>") {
    edges {
      node {
        id
        name
      }
    }
  }
}
```

## Filtering

CDash implements a filtering mechanism that allows results to be efficiently narrowed using the
`filters` argument available on most connection fields.  A filter consists of one of the
following operators, each of which compares a single field against a value:

* `eq` / `ne`: equal to / not equal to
* `gt` / `lt`: greater than / less than
* `ge` / `le`: greater than or equal to / less than or equal to
* `contains`: substring match

For example, the following query returns the project named `MyProject`:

```graphql
query {
  projects(filters: {eq: {name: "MyProject"}}) {
    edges {
      node {
        id
        name
      }
    }
  }
}
```

Two additional operators, `any` and `all`, combine a list of filters using OR/AND logic
respectively, and may be nested to construct arbitrarily complex conditions:

```graphql
query {
  projects(filters: {
    any: [
      {eq: {name: "MyProject"}}
      {eq: {name: "MyOtherProject"}}
    ]
  }) {
    edges {
      node {
        name
      }
    }
  }
}
```

The `has` operator allows filtering based on the fields of a related object.  For example, the
following query returns every project containing at least one build named `nightly`:

```graphql
query {
  projects(filters: {
    has: {
      builds: {eq: {name: "nightly"}}
    }
  }) {
    edges {
      node {
        name
      }
    }
  }
}
```

Only one operator, and only one field within that operator, may be used per filter object.
Multiple conditions must be combined using `any` or `all`.  Not every field supports filtering.
Consult GraphiQL's Docs panel to determine which fields are available on a given type's
`...FilterInput`.

## Sorting

Any field that supports filtering also accepts an `orderBy` argument for sorting its results.
Each entry in the list specifies a `column`, which may be any filterable field on the returned
type, and an `order` of either `ASC` or `DESC`.  Multiple entries may be specified to sort by more
than one column:

```graphql
query {
  projects(orderBy: [{column: NAME, order: ASC}]) {
    edges {
      node {
        name
      }
    }
  }
}
```

`orderBy` and `filters` may be combined within the same query.

## Using the API from Python

Python applications can interact with the CDash GraphQL API using the
[`gql`](https://gql.readthedocs.io) library, authenticating with a token as described above.
Refer to the documentation for installation instructions, transport configuration, and usage
examples.
