# Templates

The wiki editor has a `Templates` toolbar item that allows you to insert template text into the editor.

Templates are wiki pages created with names like [wiki:Templates/Example Templates/Example].  Try clicking on that link and creating a template page.

Each wiki page found in the `Templates` directory is pulled into the set of
templates in the editor toolbar.  By default, this list is cached for up to an
hour, so newly added or deleted templates may take this long to show up; you
can ask your users to double-refresh their browser to force this list to update
sooner.

The template text is loaded via Ajax when it is first used in a page, so
template edits are visible and take effect on the next page load or reload.

