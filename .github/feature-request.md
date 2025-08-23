We will start implementing the backbone of the application with its information architecture in mind. Filament will be used to manage page contents. We have filament fabricator installed (#fetch https://v2.filamentphp.com/plugins/fabricator) to handle layouts and page blocks.

We need a filament resources for:
- Pillar pages: new filament fabricator layout for pillar pages. The resource form should have fields what game it is related to (use GameEnum), a title, and content. The content will be page blocks from filament fabricator. You don't need to implement page blocks for now, only the PageBuilder.
