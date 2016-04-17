# PHP Backblaze B2 API

This is a custom, specialized library for talking to the Backblaze B2
API. It is only intended for use by Phabricator, but might be
generally useful to someone else.

To run the demo:

```bash
$ cp b2-example.sample.json b2-example.json
$ $EDITOR b2-example.json # fill in your credentials
$ php b2-example.php
```

It should upload the `testing.txt` file, echo it, and delete it.
