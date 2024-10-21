@contentrepository @adapters=DoctrineDBAL
Feature: Rebasing auto-created nodes works

  Tests a bugfix for auto-created nodes, which appeared in Neos UI.

  Basic fixture setup is:
  - root workspace with a single "root" node inside.
  - then, a nested workspace user-test is created
  - In the user-test workspace, we create a new node with auto-created child nodes WITHOUT SPECIFYING THE
  NESTED NODE IDENTIFIERS (tetheredDescendantNodeAggregateIds)
  - then, for the auto-created child node, set a property.
  - finally, try to rebase the whole thing.

  This operation only is successful if the auto-created child node's node identifier is the same during the
  rebase as in the original content stream -- and this was not the case in Neos for some time.

  Background:
    Given using no content dimensions
    And using the following node types:
    """yaml
    'Neos.ContentRepository.Testing:Content':
      childNodes:
        foo:
          type: 'Neos.ContentRepository.Testing:ContentNested'
    'Neos.ContentRepository.Testing:ContentNested':
      properties:
        text:
          type: string
    """
    And using identifier "default", I define a content repository
    And I am in content repository "default"
    And the command CreateRootWorkspace is executed with payload:
      | Key                | Value           |
      | workspaceName      | "live"          |
      | newContentStreamId | "cs-identifier" |
    And I am in workspace "live" and dimension space point {}
    And the command CreateRootNodeAggregateWithNode is executed with payload:
      | Key             | Value                         |
      | nodeAggregateId | "lady-eleonode-rootford"      |
      | nodeTypeName    | "Neos.ContentRepository:Root" |

    And the command CreateWorkspace is executed with payload:
      | Key                | Value                |
      | workspaceName      | "user-test"          |
      | baseWorkspaceName  | "live"               |
      | newContentStreamId | "user-cs-identifier" |

  Scenario: complex scenario (to reproduce the bug) -- see the feature description
    # USER workspace: create a new node with auto-created child nodes
    When the command CreateNodeAggregateWithNodeAndSerializedProperties is executed with payload:
      | Key                       | Value                                    |
      | workspaceName             | "user-test"                              |
      | nodeAggregateId           | "nody-mc-nodeface"                       |
      | nodeTypeName              | "Neos.ContentRepository.Testing:Content" |
      | nodeName                  | "mcnodeface"                             |
      | originDimensionSpacePoint | {}                                       |
      | parentNodeAggregateId     | "lady-eleonode-rootford"                 |
    And I am in workspace "user-test" and dimension space point {}
    Then I expect node aggregate identifier "nody-mc-nodeface" to lead to node user-cs-identifier;nody-mc-nodeface;{}
    When I get the node at path "mcnodeface/foo"
    And I expect this node to be a child of node user-cs-identifier;nody-mc-nodeface;{}

    # - then, for the auto-created child node, set a property.
    When the command "SetSerializedNodeProperties" is executed with payload:
      | Key                       | Value                                          |
      | workspaceName             | "user-test"                                    |
      | nodeAggregateId           | $this->currentNodeAggregateId                  |
      | originDimensionSpacePoint | {}                                             |
      | propertyValues            | {"text": {"value":"Modified","type":"string"}} |
      | propertiesToUnset         | {}                                             |

    # user ws must be outdated!!! otherwise cheesy

    # rebase of SetSerializedNodeProperties
    # More than one node anchor point for content stream: b2a1d336-38a7-4183-815c-c49b2c7eba8c, node aggregate id: 7ef3c166-16d5-4aed-b8cb-5a44670607b7 and origin dimension space point: [] – this should not happen and might be a conceptual problem!
    When the command RebaseWorkspace is executed with payload:
      | Key           | Value       |
      | workspaceName | "user-test" |
      | rebasedContentStreamId | "user-cs-rebased" |
    # This should properly work; no error.

