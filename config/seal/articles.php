<?php

declare(strict_types=1);

use CmsIg\Seal\Schema\Field\DateTimeField;
use CmsIg\Seal\Schema\Field\FloatField;
use CmsIg\Seal\Schema\Field\IdentifierField;
use CmsIg\Seal\Schema\Field\TextField;
use CmsIg\Seal\Schema\Index;

return new Index('articles', [
    'id' => new IdentifierField('id'),
    'title' => new TextField('title', searchable: true),
    'contentText' => new TextField('contentText', searchable: true),
    'summary' => new TextField('summary', searchable: true),
    'sourceName' => new TextField('sourceName', searchable: true, filterable: true),
    'categorySlug' => new TextField('categorySlug', searchable: false, filterable: true),
    'score' => new FloatField('score', sortable: true),
    'fetchedAt' => new DateTimeField('fetchedAt', sortable: true),
]);
