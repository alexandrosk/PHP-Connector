<?php
/**
 * This is a PHP script meant to be run in the console, focused on showing
 * the connector and not dealing too much with error handling, routes,
 * templates, etc.
 *
 * Note that the actual functionality besides setting up the connector is
 * implemented in the classes mentioned in the {@see $examples} variable.
 *
 * For a fuller example, check out the examples/app directory, where you can
 * find a runnable Symfony website showing a more complete integration.
 */

namespace Loop54\API\Examples;

require_once(__DIR__ . '/../vendor/autoload.php');
require_once('Config.php');

/**
 * Check whether or not the search query made sense to the engine, and
 * suggest alternative spellings if they exist.
 *
 * @param $response \Loop54\API\SearchResponse
 */
function checkSearchResponse($response)
{
    // CODE SAMPLE search-check-results BEGIN
    if (!$response->getMakesSense()) {
        echo 'We did not understand your query' . PHP_EOL;
    }
    $spellingSuggestions = $response->getSpellingSuggestions();
    if (!empty($spellingSuggestions)) {
        $queries = array_map(
            function ($suggestion) {
                return $suggestion->getQuery();
            },
            $spellingSuggestions
        );
        $suggestions = join(', ', $queries);
        echo 'Did you mean: ' . $suggestions . '?' . PHP_EOL;
    }
    // CODE SAMPLE END
}


/**
 * Construct a search query, sort the results on name attribute, skip the
 * first three results, take two results after that, and define two distinct
 * facets on categories.
 *
 * @param $connector \Loop54\API\Client
 *
 * @return \Loop54\API\SearchRequest
 */
function searchRequest($connector)
{
    // CODE SAMPLE search-full BEGIN
    $request = $connector->search('meat');
    $request->resultsOptions()
        ->sortBy(
            \Loop54\API\ResultsOptions::TYPE_ATTRIBUTE,
            \Loop54\API\ResultsOptions::ORDER_ASC,
            'Name'
        )
        ->skip(3)
        ->take(2)
        ->addDistinctFacet(
            'Category',
            'Category2'
        );

    /* Actually perform the search query */
    $response = $connector->query($request);

    // INJECT SAMPLE search-check-results BEGIN
    checkSearchResponse($response);
    // INJECT SAMPLE END

    /* Get the total result count, which may help with pagination */
    $count = $response->getRaw()->getResults()->getCount();
    $current_count = sizeof($response->getResults());
    echo 'Found in total ' . $count . ' results. '
        . 'Showing ' . $current_count . ':' . PHP_EOL;

    /* Print all results in this response */
    foreach ($response->getResults() as $entity) {
        $id = $entity->getId();

        //we know that this attribute exists
        $title = $entity->getAttribute('Title');

        //this attribute may not exist, check if it does before using it
        if($entity->hasAttribute('Does not exist'))
            echo $entity->getAttribute('Does not exist');

        echo $id . ': ' . $title . PHP_EOL;
    }

    /* Print all related results in this response */
    echo PHP_EOL . 'You might also like these:' . PHP_EOL;
    foreach ($response->getRelatedResults() as $entity) {
        $id = $entity->getId();
        $title = $entity->getAttribute('Title');
        echo $id . ': ' . $title . PHP_EOL;
    }
    // CODE SAMPLE END
}

function autoCompletion($connector)
{
    // CODE SAMPLE autocomplete-full BEGIN
    /* Request autocompletions for a prefix */
    $request = $connector->autocomplete('a')->take(2);

    /* Actually perform the request */
    $response = $connector->query($request);

    echo 'Items starting with prefix:' . PHP_EOL;
    /* Print all (unscoped) completions for this prefix */
    foreach ($response->getUnscopedResults() as $result) {
        echo $result['query'] . PHP_EOL;
    }
    // CODE SAMPLE END

    echo PHP_EOL;

    // CODE SAMPLE autocomplete-scoped BEGIN
    echo 'Suggested scopes for most popular result:' . PHP_EOL;
    $scopedQuery = $response->getScopedResult();
    if ($scopedQuery != null) {
        echo $scopedQuery['query'] . ' where '
            . $scopedQuery['scopeAttributeName']
            . ' is one of ' . implode(', ', $scopedQuery['scopes'])
            . PHP_EOL;
    }
    // CODE SAMPLE END
}

function categoryListing($connector)
{
    // CODE SAMPLE categorylisting-full BEGIN
    /* Configure a request to get the 9 first items in the Meat category */
    $request = $connector->getEntitiesByAttribute('Category', 'Meat');
    $request->resultsOptions()
        ->skip(0)
        ->take(9);

    /* Actually perform the request */
    $response = $connector->query($request);

    /* Print all results in this response. */
    echo 'Items in category:' . PHP_EOL;
    foreach ($response->getResults() as $entity) {
        $id = $entity->getId();
        $title = $entity->getAttribute('Title');
        echo $id . ': ' . $title . PHP_EOL;
    }
    // CODE SAMPLE END

    // CODE SAMPLE categorylisting-sorting BEGIN
    /* Sort by ascending values in the "Price" attribute */
    $request->resultsOptions()->sortBy(
        \Loop54\API\ResultsOptions::TYPE_ATTRIBUTE,
        \Loop54\API\ResultsOptions::ORDER_ASC,
        'Price'
    );

    /* Sorting on multiple criteria is possible, but uses a clunky interface */
    $request->resultsOptions()->getRaw()->setSortBy([
        new \Loop54\API\OpenAPI\Model\EntitySortingParameter([
            'type' => \Loop54\API\ResultsOptions::TYPE_ATTRIBUTE,
            'order' => \Loop54\API\ResultsOptions::ORDER_DESC,
            'attribute_name' => 'Price'
        ]),
        new \Loop54\API\OpenAPI\Model\EntitySortingParameter([
            'type' => \Loop54\API\ResultsOptions::TYPE_POPULARITY,
            'order' => \Loop54\API\ResultsOptions::ORDER_DESC
        ])
    ]);
    // CODE SAMPLE END

    // CODE SAMPLE categorylisting-filter BEGIN
    $filters = new \Loop54\API\FilterParameter();

    $request = $connector->getEntitiesByAttribute(
        'Manufacturer',
        'Sweet Home Alabama'
    );
    /* Filter results to only see new items with a price */
    $request->resultsOptions()->filter(
        $filters->attributeExists('Price')
        ->and($filters->attribute('IsNew', true))
    );
    // CODE SAMPLE END

    /* Actually perform the request */
    $response = $connector->query($request);

    /* Print all results in this response. */
    echo 'Filtered items in category:' . PHP_EOL;
    foreach ($response->getResults() as $entity) {
        $id = $entity->getId();
        $title = $entity->getAttribute('Title');
        echo $id . ': ' . $title . PHP_EOL;
    }
}

function getEntities($connector)
{
    // CODE SAMPLE getentities-filter BEGIN
    $filters = new \Loop54\API\FilterParameter();

    $request = $connector->getEntities();
    /* Filter results to only see new items with a price */
    $request->resultsOptions()->filter(
        $filters->attributeExists('Price')
        ->and($filters->attribute('IsNew', true))
    );
    // CODE SAMPLE END

    /* Actually perform the request */
    $response = $connector->query($request);

    /* Print all results in this response. */
    echo 'Filtered items:' . PHP_EOL;
    foreach ($response->getResults() as $entity) {
        $id = $entity->getId();
        $title = $entity->getAttribute('Title');
        echo $id . ': ' . $title . PHP_EOL;
    }
}

function getRelatedEntities($connector)
{
    // CODE SAMPLE getrelatedentities BEGIN

    $request = $connector->getRelatedEntities($connector->entity('Product', 12));
    /* Take only 10 items */
    $request->resultsOptions()->take(10);
//     $request->relationKind('similar');    // this line is commented because "helloworld" engine does not support relationKind yet
    
    // CODE SAMPLE END

    /* Actually perform the request */
    $response = $connector->query($request);

    /* Print all results in this response. */
    echo 'Taken items:' . PHP_EOL;
    foreach ($response->getResults() as $entity) {
        $id = $entity->getId();
        $title = $entity->getAttribute('Title');
        echo $id . ': ' . $title . PHP_EOL;
    }
}

function getBasketRecommendations($connector)
{
    echo 'Skipped, not supported in the helloworld engine yet' . PHP_EOL;
    return;
    // CODE SAMPLE get-basket-recommendations-full BEGIN
    $request = $connector->getBasketRecommendations([
        $connector->entity('Product', 12),
        $connector->entity('Product', 13)
    ]);

    /* Take only 10 items */
    $request->resultsOptions()->take(10);

    /* perform the request to the engine */
    $response = $connector->query($request);

    /* Print all results in this response. */
    foreach ($response->getResults() as $entity) {
        $id = $entity->getId();
        $title = $entity->getAttribute('Title');
        echo $id . ': ' . $title . PHP_EOL;
    }
    // CODE SAMPLE END
}

function eventCreation($connector)
{
    $productId = 12;

    // CODE SAMPLE create-events BEGIN
    /* Indicate to the engine that user has shown interest in product. */
    $connector->clickEvent($connector->entity('Product', $productId));

    /* Indicate to the engine that user has added product to cart. */
    $connector->addToCartEvent($connector->entity('Product', $productId));

    /* Set up a multi-event request from an entire purchase order. */
    $purchase = $connector->concatEvents(
        $connector->createEvent('purchase')
           ->entity($connector->entity('Product', $productId))
           ->quantity(5)
           ->revenue(249),
        $connector->createEvent('purchase')
           ->entity($connector->entity('Product', $productId + 1))
    );

    /* Send the event */
    $connector->query($purchase);
    // CODE SAMPLE END

    // CODE SAMPLE create-events-custom-user-id BEGIN
    $getUserId = function () {
        return 'custom-user-id';
    };
    $connector->withUserId($getUserId)
        ->purchaseEvent($connector->entity('Product', $productId));
    // CODE SAMPLE END

    echo 'All events created' . PHP_EOL;
}

function customUserId($connector)
{
    $getUserId = function () {
        return 'custom-user-id';
    };
    // CODE SAMPLE custom-user-id BEGIN
    $connector = $connector->withUserId($getUserId);
    // CODE SAMPLE END
}

function faceting($connector)
{
    // CODE SAMPLE faceting-multiple-facets BEGIN
    $request = $connector->search('food');

    $request->resultsOptions()
        ->addDistinctFacet('Organic', 'Organic')
        ->addDistinctFacet('Category', 'Category');
    // CODE SAMPLE END

    // CODE SAMPLE faceting-distinct-facet BEGIN
    $request->resultsOptions()
        ->addDistinctFacet('Manufacturer', 'Manufacturer', ['Early']);
    // CODE SAMPLE END

    // CODE SAMPLE faceting-range-facet BEGIN
    $request->resultsOptions()
        ->addRangeFacet('Price', 'Price', 10, 60);
    // CODE SAMPLE END

    $response = $connector->query($request);

    echo 'Facets of interest:' . PHP_EOL;
    // CODE SAMPLE render-distinct-facets BEGIN
    $distinctFacetsToDisplay = ['Manufacturer', 'Category', 'Organic'];
    $facets = $response->getFacets();
    foreach ($facets as $facet) {
        if (in_array($facet['name'], $distinctFacetsToDisplay)) {
            echo $facet['name'] . ': ' . PHP_EOL;
            foreach ($facet['items'] as $option) {
                echo '    [' . ($option->selected ? 'X' : ' ') . '] '
                    . $option->item . ' (' . $option->count . ')' . PHP_EOL;
            }
        }
    }
    // CODE SAMPLE END
}

function syncing($connector)
{
    echo 'Syncing...' . PHP_EOL;
    // CODE SAMPLE sync BEGIN
    $connector->sync();
    // CODE SAMPLE END
    echo 'Sync complete' . PHP_EOL;
}

function customRequest($connector)
{
    // CODE SAMPLE custom-request BEGIN
    $connector->doCustomRequest('client.rebelalliance.deltapush', [
        'Data' => [
            'products' => [
                [ 'id' => 22, 'name' => 'Citadel Security Car', 'price' => 39 ],
                [ 'id' => 23, 'name' => 'X-34 Landspeeder', 'price' => 59 ],
                [ 'id' => 24, 'name' => 'T-65B X-wing', 'price' => 40 ]
            ]
        ]
    ]);
    // CODE SAMPLE END
}

try {
    $remoteClientInfo = new \Loop54\API\RemoteClientInfo\SimpleClient(
        '127.0.0.1',
        'simple remote client',
        'no-referer'
    );
    $connector = new \Loop54\API\Client(
        Config\ENDPOINT,
        Config\API_KEY,
        $remoteClientInfo
    );

    echo '--------------------SEARCH----------------------' . PHP_EOL;
    searchRequest($connector);
    echo '------------------AUTOCOMPLETE------------------' . PHP_EOL;
    autoCompletion($connector);
    echo '---------------------GEBA-----------------------' . PHP_EOL;
    categoryListing($connector);
    echo '------------------GETENTITIES-------------------' . PHP_EOL;
    getEntities($connector);
    echo '---------------GETRELATEDENTITIES---------------' . PHP_EOL;
    getRelatedEntities($connector);
    echo '-----------GETBASKETRECOMMENDATIONS-------------' . PHP_EOL;
    getBasketrecommendations($connector);
    echo '------------------CREATEEVENTS------------------' . PHP_EOL;
    eventCreation($connector);
    echo '--------------------FACETING--------------------' . PHP_EOL;
    faceting($connector);
    echo '--------------------SYNCING---------------------' . PHP_EOL;
    syncing($connector);
    echo '--------------------------------------------' . PHP_EOL;
} catch (Exception $ex) {
    print_r($ex->getMessage());
}
