# PRIME ReVolution CLI

A Symfony 6 command line interface to scrape the old (2005-2012ish) PRIME e-wrestling website for content related to wrestling cards and supershows. Because of the nature of this tool and its use to fetch legacy content, it is very much a "run once" type of application. It was built because the people in charge of running that organization asked if there was an easy way to get legacy content from the website, as they no longer had credentials to access the administrative portal, nor were they able to secure a backup of the database from the person hosting the site.

**NOTE:** Some of the content there is problematic even in the context of the era it was written in. The existence of this app does not endorse any of this content, it exists simply as an archival method.

## Design Considerations

### 1. Outdated Markup

The source website is very much a product of its time, and is not structured in a semantic fashion. Despite being updated in the later half of the aughts, there is still an overreliance on tables on some pages. Further, there were little rules or restrictions placed on site users when it came to authoring their content, and no rules exist to keep a consistent styling within the context of an on-card segment. To that end the people who asked if scraping the site was possible were okay with the idea of some individual formatting choices coming along for the ride, provided they had the actual text.

![Screen Shot 2025-02-05 at 11 39 14 AM](https://github.com/user-attachments/assets/4c104bac-6f06-40c7-b974-34722f4ca827)


![Screen Shot 2025-02-05 at 11 39 56 AM](https://github.com/user-attachments/assets/eeae4ff0-a27b-4fa1-893e-bfd9ea8c64d2)

### 2. Unusual Configuration

You will see places in this code where an exception is caught when trying to load a document and yet the body content is still fetched. This is due to the way in which this site behaves. It is, to date, the only website I have seen where the server is generating an HTTP status code of `500` while still operating normally to the casual user. Because of the lax conditions that the people who requested this script had for its behavior, this was not something that was investigated fully.

### 3. Command Output

All file system paths assume that I am the only person running this code.

## What's e-wrestling?

The easiest way to describe it is like D&D for pro wrestling nerds. You create a character, develop their personality and style, and then are booked in matches against other characters. Those matches are judged through writing. Each participant writes one story that is graded on the following criteria:

* Entertainment and creativity
* Character development
* Match relevance
* Readability

The person whose character scores higher wins.

