# SetFix
A macro-extensible algebra for querying and constructing finite sets

## Syntax
### Sets
```
*     : Select all elements in the universe, this set is called Universe
(...) : Create a set from the inner query, this set is called a grouping 
```
### Set Operations
```
! : Exclusion (NOT)
| : Union (OR)
& : Intersection (AND)
: : Filter by metadata predicate
```
### Filter Operations
```
<,<=,=,>=,> : Standard comparison operations
```
### Additional Operations
```
;   : Expression Termination
:=  : Assignment
::  : Signature
```
## Examples
### Setup
```php
$set = [
    ['code' => 'D', 'name' => 'Dog', 'age' => 3],
    ['code' => 'C', 'name' => 'Cat', 'age' => 5],
    ['code' => 'F', 'name' => 'Frog', 'age' => 1]
];

$qr = SetFix::fromArray(
    set: $set,
    identifierCallback: static fn(array $item) => $item['code']
);

$selection = $qr->query(...);
```
### Queries
## Selection
```
*D|C            => ['Dog', 'Cat']
*:age<5         => ['Dog', 'Frog']
*:age>1&:age<5  => ['Dog']
(*C|F):age>1    => ['Cat']
*D,*D|C,*D|C|F  => [['Dog'], ['Dog', 'Cat'], ['Dog', 'Cat', 'Frog']]
```
## Grammar
```
querySet            := query (';' query)*
query               := set selection?
set                 := universe|grouping
grouping            := '(' query ')'
selection           := union
union               := intersection ('|' intersection)*
intersection        := unary ('&' unary)*
unary               := '!' unary | filter
filter              := metadataPredicate | term
metadataPredicate   := ':' metaId comparison term
term                := itemId | scalar
comparison          := < | <= | = | >= | >
universe            := *
scalar              := string | float | int
string              := '"' (a-zA-Z_-.)* '"' 
float               := int '.' (0-9)+
int                 := '-'? (0-9)*

statement             := definition* construction?
definition            := assignment | macro ';'
assignment            := groupings ':=' set ';'
macro                 := macroId params '::' macroBody
macroBody             := assignment* query
construction          := '{' construction* '}' | sets
sets                  := set ('|' set)*
set                   := universe|grouping
```
## Debug
Debug output can be activated by passing true to the debug flag when instantiating an **Evaluator**, this causes the **Evaluator** to print the AST for the parsed query in Lisp format.
```clojure
(QuerySet
  (Query
    (Universe)
    (Union
      (Intersection
        (Filter
          (MetadataPredicate
            (MetadataIdentifier "value")
            (Comparison =)
            (Term
              (Scalar "Boo"))))
      (Intersection
        (Filter
          (MetadataPredicate
            (MetadataIdentifier "value")
            (Comparison =)
            (Term
              (Scalar "Abra")))))))))
```
## Planned Features
### Named Sets
Named Sets are a proposed feature to allow for more composable query definitions. You can assign any valid set a name
using the new assignment operator `:=`. Your named set can then be used many times within further queries.

Named Sets can make use of other named sets to achieve optimal composability, see below:
```
children := *:age<16;
boys := children:gender='male';
boys;
```
Note that Named Sets act as AST node substitutions and thus are evaluated lazily. See how they fit into an AST below:
```clojure
(Statement
  (NamedSet
    (SetIdentifier "children")
    (Query
      (Universe)
      (Union
        (Intersection
          (Filter
            (MetadataPredicate
              (MetadataIdentifier "age")
              (Comparison <)
              (Term
                (Scalar 16))))))))
  (NamedSet
    (SetIdentifier "boys")
    (Query
      (SetIdentifier "children")
      (Union
        (Intersection
          (Filter
            (MetadataPredicate
              (MetadataIdentifier "gender")
              (Comparison =)
              (Term
                (Scalar "male"))))))))
  (QuerySet
    (Query
      (SetIdentifier "boys"))))
```
Resolves to
```clojure
(Statement
  (NamedSet
    (SetIdentifier "boys")
    (Query
      (Grouping
        (Query
          (Universe)
          (Union
            (Intersection
              (Filter
                (MetadataPredicate
                  (MetadataIdentifier "age")
                  (Comparison <)
                  (Term
                    (Scalar 16))))))))
      (Union
        (Intersection
          (Filter
            (MetadataPredicate
              (MetadataIdentifier "gender")
              (Comparison =)
              (Term
                (Scalar "male"))))))))
  (QuerySet
    (Query
      (SetIdentifier "boys"))))
```
Resolves to
```clojure
(Statement
  (QuerySet
    (Query
      (Grouping
        (Query
          (Grouping
            (Query
              (Universe)
              (Union
                (Intersection
                  (Filter
                    (MetadataPredicate
                      (MetadataIdentifier "age")
                      (Comparison <)
                      (Term
                        (Scalar 16))))))))
          (Union
            (Intersection
              (Filter
                (MetadataPredicate
                  (MetadataIdentifier "gender")
                  (Comparison =)
                  (Term
                    (Scalar "male")))))))))))
```
### Macros
Macros are a proposed feature that will further improve composability in SetFix. Unlike Named Sets, Macros do not need
to be valid Sets, they can be anything thereby facilitating arbitrary code reuse. Additionally Macros can take
parameters, introducing interesting new ways to write SetFix code.

Macros can be defined using the new Signature operator `::`. The syntax for defining a Macro is as follows:
```
macroId (paramId ' ')* :: body;
```
Below are a few examples of Macro definitions:
```
old :: :age>60;
between metaId a b :: :metaId>a&:metaId<b;
inSchool :: [between age 4 16];
*[inSchool]
```
Notice the square-bracket syntax in the above example, this is SetFix' Macro calling syntax, it is formalised below:
```
[macroId (param ' ')*]
```
### Extended Set Operations
#### Shift
Shift allows you to drop the first, or the last n elements from a set as follows:
```
(*A|B|C)>>1 # Evaluates to [B, C]
(*A|B|C)<<1 # Evaluates to [A, B]
```
#### Stride
Stride allows you to select only every nth element from a set, see below:
```
(*A|B|C)~2  # Evaluates to [A, C]
(*A|B)~2    # Evaluates to [A]
(*A|B|C)~1  # Evaluates to [A, B, C]
(*A|B|C)~0  # Evaluates to []
```
### Constructions
Constructions are a proposed feature that will facilitate structured output from SetFix queries. New stuff:
#### Primitive Construction
The fundamental building block of constructions is Primitive Construction, this is achieved with the following syntax:
```
{Set? (' ' Set)*}
```
Example
```
{"This" "Is" "A" "Construction"}  # Evaluates to ["This", "Is", "A", "Construction"]
{(*:age<10) (*:age>5)}
```
#### Zip Operator `/`
The Zip operator takes the lefthand set and combines it with the righthand set on an positional level to produce pairs.
Note that the lefthand set and the righthand set must have the same number of items otherwise the operation is invalid
and an error will be thrown. The syntax is as follows:
```
Set/Set
```
Example
```
(*A|B|C)/(*D|E|F) # Evaluates to [[A,D], [B,E], [C,F]]
```
An huge benefit provided by the Zip Operator is made apparent when you marry it with Macros and Extended Set Operations, below is the implementation of the `kv` Macro (Key-Value):
```
kv items :: keys := (items~2)/((items>>1)~2)
```
This macro allows for the easy creation of nested constructions:
```
[kv
  "heightGrouped" [kv "tall" tall "short" short]
  "all" juniors]
```
Resolves to
```
{"heightGrouped" "all"}/{{"tall" "short"}/{tall short} juniors}
```
Which evaluates to the following, that can easily be converted to proper host language dictionaries/hashmaps
```
[
  ["heightGrouped, [["tall", tall], ["short", short]]],
  ["all", juniors]
]
```
#### Unzip Operator `\`
```
[[a,b,c], [1,2,3]]\0            # [] 
[[a,b,c], [1,2,3]]\1            # [[a,1]]
[[a,b,c], [1,2,3]]\2            # [[a,1], [b,2]]
[[a,b,c], [1,2,3], [i,j,k]]\3   # [[a,1,i], [b,2,j], [c,3,k]]
```
#### Product Operator `//`
```php
$users = [
    'a' => ['id' => 0, 'name' => 'Billy Bob'],
    'b' => ['id' => 1, 'name' => 'Joe Bloggs'],
    'c' => ['id' => 2, 'name' => 'John Smith'] 
];
$orders = [
    'a' => ['id' => 0, 'userId' => 0, 'desc' => '3 Hammers'],
    'b' => ['id' => 1, 'userId' => 0, 'desc' => '12 Nails'],
    'c' => ['id' => 2, 'userId' => 2, 'desc' => 'A Sandwich'] 
];
$sf = SetFix::fromArrays($users, $orders);
echo $sf->query(...);
```
```setfix
lj leftSet leftField rightSet rightField :: 
  product                         := leftSet//rightSet;
  productLeft productRight        := product\2;
  matchedLeft                     := productLeft:leftField = productRight.rightField;
  matchedRight                    := productRight:rightField = productLeft.leftField;
  matches                         := matchedLeft/matchedRight;
  unmatchedLeft                   := leftSet!matchedLeft;
  padded                          := unmatchedLeft//();
  matches|padded;

lj leftSet leftField rightSet rightField :: 
  product                         := (leftSet//rightSet)\2
  matched                         := product:0.leftField = 1.rightField
  matchedLeft                     := matched\1
  unmatchedLeft                   := leftSet!matchedLeft
  padded                          := unmatchedLeft//()
  matches|padded
```
Stepped Breakdown
`product := left//right`
```
[[a,a], [a,b], [a,c]. [b,a], [b,b], [b,c], [c,a], [c,b], [c,c]]    # Left value is identity from set $users, right value is identity from set $orders
```
`[productLeft, productRight] := product\2`
```
[a,a,a,b,b,b,c,c,c]                                                # Left identities into set productLeft
[a,b,c,a,b,c,a,b,c]                                                # Right identities into set productRight
```
`matchedLeft := productLeft:leftField = productRight.rightField`
```
[a,a,c]
```
`matchedRight := productRight:rightField = productLeft.leftField`
```
[a,b,c]
```
`matches := matchedLeft/matchedRight`
```
[[a,a], [a,b], [c,c]]
```
`unmatchedLeft := leftSet!matchedLeft`
```
[b]
```
`padded := unmatchedLeft//()`
```
[[b,()]]
```
`matches|padded`
```
[[a,a], [a,b], [c,c], [b,()]]
```

## Reimplementation

SetFix becomes Moss. Moss is a collection based langauge that values terseness, composabilty and ease of use
Borrowing from SetFix:
- Set operations
- Operator driven syntax

But adding:
- First class functions
- Constructions as core data structure
- Recursion

```
map := [f xs -> xs ~> {} [acc item -> acc|{[f item]}]];
flatMap := [f xs -> xs ~> {} [acc item -> acc|[f item]]];
filter := [f xs -> xs ~> {} [acc item -> if [f item] then acc|{item}]];
count := [f xs -> xs ~> 0 [acc item -> if [f item] then acc + 1]];

program             := statement*
statement           := (definition | expression) ';'
definition          := id ':=' expression
expression          := conditional
conditional         := 'if' pipeline 'then' pipeline ('else' pipeline)? | pipeline
pipeline            := concatenation ('~>' reducerBody)*
concatenation       := comparison ('|' comparison)*
comparison          := arithmetic (comparisonOperator arithmetic)?
arithmetic          := term (addOperator term)*
term                := unary (mulOperator mul)*
unary               := unaryOperator unary | primary
primary             := application | '(' expression ')' | construction | id | symbol | scalar
application         := '[' call | lambda ']'
reducerBody         := init (id|lambda)
lambda              := params '->' lambdaBody
params              := id*
lambdaBody          := expression
call                := expression (',' expression)*
id                  := atom ('.' atom)*
symbol              := ':' atom
addOperator         := '+' | '-'
mulOperator         := '*' | '/'
comparisonOperator  :=  '<' | '<=' | '=' | !=' | '>' | '>='
construction        := '{' constructionBody '}'
constructionBody    := constructionElement? (',' constructionElement)*
constructionElement := (construction | id | symbol | scalar)
atom                := (a-zA-Z) (a-zA-Z0-9_-)*

[kv [pair 'name' 'Jeremy']]
people := {                       # Define construction called people
  {   
    {:name 'Jeremy},               # Store data as key-value constructions
    {:height 2.1},
    {:age 19}
  }
  {
    {:name 'Alex},                 # Strings with no whitespace can just be prefixed (no closing quote)
    {:height 1.95},
    {:age 20}
  }
  {
    {:name 'Matthew},
    {:height 2.05},
    {:age 18}
  }
  {
    {:name 'Matthew},
    {:height 1.80},
    {:age 17}
  }
};

map construction transformation :: construction ~> {} [@acc | [transformation @]]

tallPeople := people:height > 2.0;        # Filter people by predicate
jAndA := people:name = 'Jeremy|'Alex;     # Filter set-style using union within predicate
intersection := tallPeople&jAndA;         # Get intersection of two constructions
totalAge := tallPeople ~> 0 [@acc + @.height];
names := [map people [@.name]]            # Get names of people

```