# WikiFormatting

Text markup is a core feature, tightly integrating all the other parts of mtrack into a flexible and powerful whole.

mtrack uses an extended version of the [Markdown](http://daringfireball.net/projects/markdown/) text processor, based on the [PHP Markdown Extra](http://michelf.com/projects/php-markdown/extra/) implementation.

This page demonstrates the formatting syntax available anywhere WikiFormatting is allowed.


## Font Styles

| Mardown                       | Result                        |
| ----------------------------- | ----------------------------- |
| `**bold**`                    | **bold**                      |
| `__bold__`                    | __bold__                      |
| `_italic_`                    | _italic_                      |
| `*italic*`                    | *italic*                      |
| \\\*literal asterisks\\\*     | \*literal asterisks\*         |
| `**_bold italic_**`           | **_bold italic_**             |
| `*__bold italic__*`           | *__bold italic__*             |
| `___bold italic___`           | ___bold italic___             |
| `***bold italic***`           | ***bold italic***             |
| \`monospace\`                 | `monospace`                   |
| `<del>strike-through</del>`   | <del>strike-through</del>     |
| `super<sup>script</sup>`      | super<sup>script</sup>        |
| `sub<sub>script</sub>`        | sub<sub>script</sub>          |

## Headings

You can create heading by starting a line with one up to five ''hash'' characters ("#")
followed by a single space and the headline text.

The line may end with a space 
followed by any number of ''#'' characters if you like.

The heading might optionally be followed by an explicit id. If not, an
implicit but nevertheless readable id will be generated.

| Markdown                      | Result                        |
| ----------------------------- | ----------------------------- |
| `# Heading #`                 | <h1>Heading</h1>              |
| `## Subheading ##`            | <h2>Subheading</h2>           |
| `### About ''this'' ###`      | <h3>About ''this''</h3>       |

### Cross References

You can link within your document, or make specific portions of your document linkable to other documents by setting an ID on your heading:

```
### My Title ### {#myid}

[link text](#myid)
```

```markdown
### My Title ### {#myid}

[link text](#myid)
```

## Paragraphs

A new text paragraph is created whenever two blocks of text are separated by one or more empty lines.

## Lists

Markdown supports both ordered/numbered and unordered lists.
You *must* leave a blank line before the first bullet or number in the
list.

You may nest lists if you indent the nested items by at least 2 spaces
or tabs.

Example:
```

 * Item 1
   * Item 1.1
      * Item 1.1.1   
      * Item 1.1.2
      * Item 1.1.3
   * Item 1.2
 * Item 2

 1. Item 1
   * Item 1.a
   * Item 1.b
      * Item 1.b.i
      * Item 1.b.ii
 1. Item 2

```

Display:

 * Item 1
   * Item 1.1
      * Item 1.1.1   
      * Item 1.1.2
      * Item 1.1.3
   * Item 1.2
 * Item 2

 1. Item 1
   * Item 1.a
   * Item 1.b
      * Item 1.b.i
      * Item 1.b.ii
 1. Item 2


## Definition Lists


The wiki also supports definition lists.

Example:
```

llama
: some kind of mammal, with hair

ppython
: some kind of reptile, without hair
  (can you spot the typo?)
: a programming language
```

Display:

llama
: some kind of mammal, with hair

ppython
: some kind of reptile, without hair
  (can you spot the typo?)
: a programming language

Note that you need a space in front of the defined term.


## Preformatted Text

Block containing preformatted text are suitable for source code
snippets, notes and examples.

Markdown defines preformatted blocks as those that are indented by at
least 4 spaces.  The extended Markdown implemented in mtrack also
recognizes **fenced code blocks** surrounded by three back-tick
characters.

Example:
~~~
```
  def HelloWorld():
      print "Hello World"
```
~~~

Display:
```
 def HelloWorld():
     print "Hello World"
```

As an alternative to back-ticks, you may use three tilde characters instead; this is useful if your text needs to display verbatim back-ticks.

## Blockquotes & Citations

In order to mark a paragraph as blockquote, indent that paragraph with
right angle brackets:

Example:
```
> This text is a quote from someone else.
```

Display:
> This text is a quote from someone else.

You can also indicate an ongoing discussion by nesting the angle
brackets:

Example:
```
>> Someone's original text

> Someone else's reply text

My reply text
```

Display:
>> Someone's original text

> Someone else's reply text

My reply text

## Tables

Simple tables can be created like this:

```
| First Header  | Second Header |
| ------------- | ------------- |
| Row 1 Cell 1  | Row 1 Cell 2  |
| Row 2 Cell 1  | Row 2 Cell 2  |
```

Display:
| First Header  | Second Header |
| ------------- | ------------- |
| Row 1 Cell 1  | Row 1 Cell 2  |
| Row 2 Cell 1  | Row 2 Cell 2  |

Alignment:

```
| Left    |  Center   |  Right   |
| :------ | :-------: | -------: |
| 1       | 2         | 3        |
| 10      | 20        | 30       |
```

Displays:
| Left    |  Center   |  Right   |
| :------ | :-------: | -------: |
| 1       | 2         | 3        |
| 10      | 20        | 30       |

## Links

There are a couple of different ways to create links in your content; the first
of these allows you to specify the text that is displayed in place of the
destination, the second is a short way to include a link:

| Markdown                       | Result                      |
| ------------------------------ | --------------------------- |
| `[Google](http://google.com)`  | [Google](http://google.com) |
| `<http://google.com>`          | <http://google.com>         |

### Reference Style Links

Reference style links allow you to reference a link by some kind of short
identifier so that the flow of the text is not disturbed so much by the
presence of the link.  The definition of the link is typically placed somewhere
below the place where it was invoked, often at the bottom.  This style of link
is great if you need to reference the same target multiple times from your
text; you can define it once and reference it by short name throughout the
text.

Example:
```
[link text][mtrack]

[mtrack]: http://mtrack.wezfurlong.org
```

Renders as:
```markdown
[link text][mtrack]

[mtrack]: http://mtrack.wezfurlong.org
```

## Linking to mtrack objects and resources

As an extension to Markdown, rather than copying the URL for various pages in
mtrack, you may use one of a number of convenient short forms to reference
tickets, changesets and so on.  These are automatically converted into links
when they are detected in your text, but you can use an alternative syntax with
square brackets to specify link text or to be more deliberate:


| mtrack markdown           | Result                             |
| ------------------------- | ---------------------------------- |
| `[wiki:WikiStart]`        | [wiki:WikiStart]                   |
| `[wiki:WikiStart Home]`   | [wiki:WikiStart Home]              |
| `[label](wiki:WikiStart)` | [label](wiki:WikiStart)            |
| `#1` and `ticket:1`       | #1 and ticket:1                    |
| `[ticket:1 ticket 1]`     | [ticket:1 ticket 1]                |
| `[ticket:1 *italic* 1]`   | [ticket:1 *italic* 1]              |
| `[http://mtrack.wezfurlong.org mtrack]` | [http://mtrack.wezfurlong.org mtrack] |
| `report:Mine`             | [report:Mine]                      |
| `[1]` and `[changeset:1]` | [1] and [changeset:1]              |
| `[abc123]`                | [abc123]                           |
| `[changeset:user/repo,abc]` | [changeset:user/repo,abc]        |
| `milestone:1`             | milestone:1                        |
| `help:WikiFormatting`     | help:WikiFormatting                |
| `user:wez`                | user:wez                           |

## Images

The simplest way to include an image is to upload it as an attachment to the current page, and put the filename in a macro call like `[[Image(picture.gif)]]`.

In addition to the current page, it is possible to refer to other resources:

 * `[[Image(wiki:WikiFormatting:picture.gif)]]` (referring to attachment on another page)
 * `[[Image(ticket:1:picture.gif)]]` (file attached to a ticket)

And external images:

 `![mtrack author](http://wezfurlong.org/images/wezmugshot75.jpg)` 

  ![mtrack author](http://wezfurlong.org/images/wezmugshot75.jpg)

You may also use reference style links with this form of external image:

```
![mtrack author][wezmug]

[wezmug]: http://wezfurlong.org/images/wezmugshot75.jpg "Title"
```


Other parameters:

 * `[[Image(photo.jpg,200px)]]` (scale picture to be 200px wide)
 * `[[Image(photo.jpg,200px,nolink)]]` (don't generate a link to picture)
 * `[[Image(photo.jpg,200px,right)]]` (float image to right)
 * `[[Image(photo.jpg,width=200,height=300)]]` (explicitly set size)
 * `[[Image(photo.jpg,name=value,other=otherval)]]` (set arbitrary attributes on the IMG tag. Values are HTML escaped)

## Using HTML

Markdown allows you to embed HTML almost anywhere.  Ideally, you won't need to
use HTML, but you can drop it in anywhere that you need it.

```
<h1 style="text-align: right; color: blue">HTML Test</h1>
```

Display:
<h1 style="text-align: right; color: blue">HTML Test</h1>

## Block Processors

Block processors allow code blocks to be transformed in other ways than
just emitting them verbatim.  The most common use for this is to apply
syntax highlighting for example code, but other processors exist for
some more specialized scenarios.

To use a processor, append the processor name to the opening fence of your code
block as shown below.

### Syntax Highlighting

~~~
```perl
my ($test) = 0;
if ($test > 0) {
    print "hello";
}
```
~~~

```perl
my ($test) = 0;
if ($test > 0) {
    print "hello";
}
```

Alternatively, you may use a hash-bang syntax:

~~~
```
#!php
<?php
list($a, $b) = explode('@', $text);
```
~~~

```
#!php
<?php
list($a, $b) = explode('@', $text);
```

#### Syntax Highlighting Names

The following is a list of possible syntax highlighting names:

[[ListRegisteredSyntaxHighlighters]]

### Legacy Trac-style Wiki

You may use Trac-stye wiki in fenced blocked using the `trac` processor:

~~~
```trac
This is trac style wiki as described in [help:TracWikiSyntax]
```
~~~

```trac
This is trac style wiki as described in [help:TracWikiSyntax]
```

### Markdown

The `markdown` processor allows fenced blocks to contain Markdown.  This may
sound strange, but the fenced markdown is evaluated in its own container and
can be useful when writing documentation.  (It's been used in this way in this
document).

## Diagrams with ASCIIToSVG

mtrack includes [ASCIIToSVG](https://bitbucket.org/dhobsd/asciitosvg), an ASCII art diagram to SVG translator.  This is nice if you're writing technical documentation and want to have some diagrams inlined.

Here's a brief example:

~~~
```a2s
.-------------.  .--------------.
|[Red Box]    |->|[Blue Box]    |
'-------------'  '--------------'

[Red Box]: {"fill":"#aa4444"}
[Blue Box]: {"fill":"#ccccff"}
```
~~~

```a2s
.-------------.  .--------------.
|[Red Box]    |->|[Blue Box]    |
'-------------'  '--------------'

[Red Box]: {"fill":"#aa4444"}
[Blue Box]: {"fill":"#ccccff"}
```

## Comments

Comments can be added to the plain text. These will not be rendered and will not display in any other format than plain text.

~~~
```comment
Your comment here
```
~~~

```comment
Your comment here
```

## Data output from SQL command line utilities

If you have text that you want to copy and paste from a command line utility,
such as psql, then you can enclose it in the ''dataset'' processor:

~~~
```dataset
            current_query             | procpid | usename | client_addr  |     elapsed
--------------------------------------+---------+---------+--------------+-----------------
 SELECT * FROM build_mailing(59508)   |    6595 | user  | 10.16.40.80 | 00:04:24.377262
 FETCH NEXT FROM "<unnamed portal 5>" |   27597 | user  | 10.16.40.80 | 00:00:44.208982
 commit                               |   19188 | user  | 10.16.40.67 | 00:00:00.013402
 COMMIT                               |   26390 | user  | 10.16.1.56  | 00:00:00.007778
```
~~~

```dataset
            current_query             | procpid | usename | client_addr  |     elapsed
--------------------------------------+---------+---------+--------------+-----------------
 SELECT * FROM build_mailing(59508)   |    6595 | user  | 10.16.40.80 | 00:04:24.377262
 FETCH NEXT FROM "<unnamed portal 5>" |   27597 | user  | 10.16.40.80 | 00:00:44.208982
 commit                               |   19188 | user  | 10.16.40.67 | 00:00:00.013402
 COMMIT                               |   26390 | user  | 10.16.1.56  | 00:00:00.007778
```

### Summary of available processors

[[ListRegisteredBlockProcessors]]

### Summary of available macros

[[ListRegisteredMacros]]

## Footnotes

```
Paragraph text with a footnote[^fn]

[^fn]: This is the footnote text.
```

Renders as:

```markdown
Paragraph text with a footnote[^fn]

[^fn]: This is the footnote text.
```


## Horizontal Rule

Three asterisks spaced out form a rule:

Example:
```
A sentence, followed by a rule.
* * *
Another sentence.
```

Display:

A sentence, followed by a rule.
* * *
Another sentence.

