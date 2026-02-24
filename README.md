# Moss
## Introduction
Moss is a toy-language designed to prioritise composability, dynamism and simplicity. It touts:
- First-class functions via lambdas
- Heterogenerous lists as the fundamental data structure
- Runtime inspection as a core language concept
- Basic numerical operations
- A simple module system
- Basic VS Code syntax highlighting

The prototype implementation is written in PHP which is far from ideal performance-wise, but provided a frictionless developer experience.
To play with the interpreter run `docker compose exec php bin/app`
## Syntax
### Values
#### **primitives**
```
123
-123
123.456
-123.456
true
:thisIsASymbol
"This is a string"
```
Creates a primitive literal value.
#### **constructions**
```
construction := { elements: <expr>* }
```
Creates a construction value where the elements are the evaluated values of the enclosed expressions.
#### **lambda**
```
lambda := [ <params> -> <body> ]
<params> := <id>*
<body> := <stmt>*
```
Creates a lambda value that takes the specified params and body, capturing anything used from the parent scope. The params must be a series of identifiers and thus are not computable dynamically, for situations where you would want a lambda with dynamic parameters use a construction as a parameter and destructure in the body.
### Operations
#### **Binding**
```
binding := <id> := <expr>
```
Binds a value to an identifier. Bindings are immutable meaning that within a scope you may only bind to a specific **id** once. Bindings can shadow meaning that you can bind to an **id** used in the parent scope.
#### **Arithmetic**
```
arithmetic := <expr> + - / * <expr>
```
Standard infix mathematic operations: add, sub, div and mul
#### **Boolean Negation**
```
negate := !<expr>
```
#### **Inspect**
```
inspect := ?<expr>
```
Inspect value, this gives the type of evaluated right-hand expression. e.g. `:integer` or `:construction`
#### **Concatenation**
```
concatenation := <expr>|<expr>
```
Creates a new construction from the results of the evaluated left-hand expression and right-hand expression. If either of the expressions evaluate to a construction then they are spread into the new construction, e.g.:
```
1|2 = {1 2}
1|{2 3} = {1 2 3}
{1 2}|3 = {1 2 3}
{1 2}|{3 4} = {1 2 3 4}
{1 {2 3}}|{4 5} = {1 {2 3} 4 5}
```
### Control Flow
#### **conditional** 
```
conditional := if cond: <expr> then then: <expr> (else fail: <expr>)?
```
Executes **cond** and using the resultant value branches execution to **then** if it is truthy or **fail** if falsey.
#### **call**
```
call := [ callee: <expr> args: <expr>* ]
```
Calls the binding returned by **callee** as a lambda with the evaluated args
#### **reducer/pipeline**
```
reducer := input: <expr> <stage> <stage>*
stage   := ~> initial: <expr> body: <id>|<lambda> 
```
Reduce **input** by the provided stages. A stage is comprised of its initial value and body, the body can be an identifier resolving to a lambda or a lambda directly.
The body of a **stage** receives up to three arguments implicitly: 
- **accumulator** the total accumulation in the reducer stage
- **element** the current element from **input**
- **index** the index of the current element

If a cycle of the stage evaluates to a Tagged Pair with the tag `:reduced` then the stage will cease early.
### Tagged Pairs
A tagged pair is a simple 2-element construction containing a symbol (the tag) and a value. Tagged pairs are useful to pass around data with meaning. Reducers used Tagged Pairs to break early, signalling with `{:reduced <value>}` that the reduce operation is finished. They can also be used to return errors from functions. Being that they only contain two values, they are very cheap to create and destructure, they can also be nested and then unwrapped accordingly.
### Native Lambdas
For operations that need to break the fourth wall e.g. IO, timing, etc. there exists Native Lambdas, these are similar to normal lambdas in every way except their body is defined in the host language (PHP presently), and not in Moss. This allows us to cleanly surface all capabilities of the host environement in a way that stays true to the worldview of the language, high composibility and minimal magic/keywords. Below are the currently supported Native Lambdas:
#### **print**
```
[print arg: any]
```
Prints the string representation of the evaluated argument.
#### **println**
```
[println arg: any]
```
Prints the string representation of the evaluated argument, followed by a new line.
#### **load**
```
[load filename: string]
```
Executes the file specified in the current environment.
#### **loadm**
```
[loadm filename: string alias: string]
```
Loads the specified file as a module. See section Modules for more information.
#### **explode**
```
[explode string: string]
```
Converts a string to a construction containing each character from the string as an element.
#### **raise**
```
[raise error: symbol message: string]
```
Raises an exception in the evaluator with the specified error and message.
### Modules
```
[ ->
    /**
    *  Fetches element at index in collection
    *
    *  If index exceeds the length of collection then :null is returned
    *
    *  @param integer index
    *  @param construction collection
    *  @return any
    */
    at := [index collection -> 
        collection ~> :null [a e i -> 
            if i = index then { :reduced e } else a
        ]
    ]

    /**
    * Reverses the order of collection
    *
    * @param construction collection
    * @return construction
    */
    reverse := [collection ->
        collection ~> {} [a e -> e|a]
    ]

    /**
    * Destructure collection into head and tail
    *
    * @param construction collection
    * @return construction{any, construction}
    */
    hat := [collection ->
        head := [at 0 collection]
        tail := collection ~> {} [a e i -> if i = 0 then a else a|e]
        {head tail}
    ]

    /**
    * Pop the last element off the construction
    *
    * @param construction collection
    * @return construction{construction, any}
    */ 
    pop := [collection -> 
        reversed := [reverse collection]
        ht := [hat reversed]
        {[at 0 ht] [reverse [at 1 ht]]}
    ]

    [f -> 
        if f = "at" then at
        else if f = "reverse" then reverse
        else if f = "hat" then hat
        else if f = "pop" then pop
        else { :noDeclError "Requested binding " + f + " not found in conslib" }
    ]
]
```
## Grammar
```EBNF
program             = statement*;
statement           = (definition | expression) ';';
definition          = id ':=' expression;
expression          = conditional;
conditional         = 'if' pipeline 'then' pipeline ('else' pipeline)? | pipeline;
pipeline            = concatenation ('~>' reducerBody)*;
concatenation       = comparison ('|' comparison)*;
comparison          = arithmetic (comparisonOperator arithmetic)?;
arithmetic          = term (addOperator term)*;
term                = unary (mulOperator mul)*;
unary               = unaryOperator unary | primary;
primary             = application | '(' expression ')' | construction | id | symbol | scalarLiteral;
application         = '[' (call | lambda) ']';
reducerBody         = init callable;
init                = expression;
lambda              = params '->' lambdaBody;
params              = id*;
lambdaBody          = expression;
call                = callable expression*;
callable            = application | id
id                  = atom ('.' atom)*;
symbol              = ':' atom;
addOperator         = '+' | '-';
mulOperator         = '*' | '/';
comparisonOperator  =  '<' | '<=' | '=' | '!=' | '>' | '>=';
construction        = '{' constructionBody '}';
constructionBody    = constructionElement*;
constructionElement = construction | id | symbol | scalarLiteral;
atom                = (a-zA-Z) (a-zA-Z0-9_-)*;
scalarLiteral       = stringLiteral | integerLiteral | floatLiteral | booleanLiteral;
stringLiteral       = '"' any_char* '"'
                    | '\'' any_char* '\'';
integerLiteral      = [0-9]+;
floatLiteral        = [0-9]+ '.' [0-9]+;
booleanLiteral      = 'true' | 'false';
```