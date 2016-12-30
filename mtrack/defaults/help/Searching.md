# Searching mtrack 

mtrack maintains a searchable index of the textual portions of tickets
and wiki pages, so that you can quickly find that elusive note when
you need it.

## Search shortcuts 

If you type one of the following special strings into the search box
and hit the search button, instead of searching, mtrack will redirect
you to the appropriate page:


\#123
:	will take you to the ticket page for that numbered ticket

## Querying the search index 

A search query may be broken up into a series of search terms and special
operators.  

### Terms 

A query is broken up into terms and operators. There are three types of terms:

Single Term
:	is a single word such as "test" or "hello"

Phrase
:	is a group of words surrounded by double quotes such as "hello dolly".

Subquery
:	is a query surrounded by parentheses such as "(hello dolly)".

Multiple terms can be combined together with boolean operators to form complex
queries.

### Fields 

When performing a search you can either specify a field to query against, or
leave the field unspecified to query against all possible fields.

You can search specific fields by entering the field name followed by a colon, followed by the term you are looking for.

For example, if you want to search wiki content for the word "search" you might enter the following:

```
wiki:search
```

If you are looking for a ticket with a particular summary and a specific word
in the description:

```
summary:"failed open" description:"file not found"
```

Note that the following is not the same as the above, as it will only search
the summary field for the word "failed", the description field for the word
"file" and all the rest of the words will be searched against all of the
possible fields:

```
summary:failed open description:file not found
```

#### Available fields 

| Item   | Field      | Purpose
| ------ | ---------- | -------
| Ticket |summary     | The one-line ticket summary
| Ticket |description | The ticket description
| Ticket |changelog   | The changelog field
| Wiki   |wiki        | The content of the wiki page

### Wildcards 

You may use single and multiple character wildcard searches within single
terms, but not within phrase queries.

To perform a single character wildcard search, use the "?" symbol.

To perform a multiple character wildcard search, use the "*" symbol.

The single character wildcard search looks for strings that match the term with the "?" replaced by any single character.  For example, to search for "text" or "test" you can use the search:

```
te?t
```

Multiple character wildcard searches look for 0 or more characters when
matching strings against terms.  For example, to search test, tests or tester,
you can use the search:

```
test*
```

You can use "?", "*" or both at any position of the term, but wildcard matches
require a non-wildcard prefix of at least 3 characters, otherwise the search
will not be allowed to continue.

### Fuzzy Searching 

You may append the tilde "~" character to a search term to specify
that a fuzzy search be used, based on the Levenshtein Distance between
similar words.

To search for a word similar in spelling to "roam":

```
roam~
```

The above will find terms like "foam" and "roams".

Additional (optional) parameters can specify the required similarity, with
possible values being fractional numbers between 0 and 1.  As this parameter
gets closer to 1, it increases the level of similarity required between the two
words before they will match.

```
roam~0.8
```

If you do not specify the fuzzy factor, the default value of `0.5` will be
used.


### Range Searches 

Range queries allow the developer or user to match field(s) whose values are
between an upper and lower bound, either inclusively or exclusively.  Sorting
is performed lexicographically, and is not limited to numeric values.

mtrack stores dates and times in the form `YYYY-MM-DDTHH:MM:SS` so that
they can be meaningfully compared in this fashion.

To perform an inclusive range query:

```
updated:[2009-08-01 TO 2009-09-01]
```

To perform an exclusive range query:

```
summary:{bug TO feature}
```


### Proximity Searches 

To find words from a phrase that are within a certain number of words apart
from each other in a document, you can append the tilde "~" character to the
end of the phrase.  For example, to match text where the words "bug" and
"report" appear within 10 words of each other:

```
"bug report"~10
```


### Boosting a Term 

The search results are returned based on the relevance of the match, as
computed by the search engine for the terms that it found.  To boost the
relevance of a term you may use the caret "^" symbol followed by a boost factor
at the end of the term or subquery that you are searching.  The higher the
boost factor, the more relevant the term will be and the higher ranking it will
have in the results when it matches:

```
"crash trace"^4 analysis
```

### Boolean Operators 

Boolean operators allow terms to be combined through logic operators.  If you
include multiple terms in your search, and do not specify a logic operator to
combine them, then the search engine assumes that you meant to use the "OR"
operator and will match documents that match any of your criteria.

You may use parentheses to group terms together to construct complex criteria.

The following operators are defined:

#### AND 

The AND operator means that all terms in the group must match some part of the
search field(s).

```
bug AND report
```

```
"stack trace" and valgrind
```

You may use `&&` as a synonym for AND.

#### OR 

The OR operator divides the query into several optional terms.

```
bug or crash
```

You may use `||` as a synonym for OR.

#### NOT 

The NOT operator excludes documents that contain the term after NOT.

```
bug and not crash
```

You may use "!" as a synonym for NOT.

#### + #### {#required}

The "+", or "required", operator stipulates that the term after the "+" symbol
must match the document.

The following matches text that must contain the word "bug" and may contain the
word "report":

```
+bug report
```

#### - #### {#prohibit}

The "-", or "prohibit", operator excludes documents that match the term after
the "-" symbol.

This matches documents that may contain the word "bug" and that do not contain
the word "report":

```
bug -report
```

### Escaping Special Characters 

The following characters are recognized as special characters by the search
engine, and must be escaped if you need to use them as part of your search
terms:

```
+ - && || ! ( ) { } [ ] ^ " ~ * ? : \
```

The "+" and "-" characters are only special when they appear at the start or
end of a search term and do not need to be escaped when they appear in the
middle of a term.

The backslash character `\` can be used to escape these special characters.
For example, if you intend to search for `(1+1):2`:

```
\(1\+1\)\:2
```

