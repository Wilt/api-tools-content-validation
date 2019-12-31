<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-content-validation for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-content-validation/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-content-validation/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\ApiTools\ContentValidation;

use Laminas\ApiTools\ContentNegotiation\ParameterDataContainer;
use Laminas\ApiTools\ContentValidation\ContentValidationListener;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Request as HttpRequest;
use Laminas\InputFilter\Factory as InputFilterFactory;
use Laminas\InputFilter\InputFilter;
use Laminas\Mvc\MvcEvent;
use Laminas\Mvc\Router\RouteMatch;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\Request as StdlibRequest;
use PHPUnit_Framework_TestCase as TestCase;

class ContentValidationListenerTest extends TestCase
{
    public function testAttachesToRouteEventAtLowPriority()
    {
        $listener = new ContentValidationListener();
        $events = $this->getMock('Laminas\EventManager\EventManagerInterface');
        $events->expects($this->once())
            ->method('attach')
            ->with(
                $this->equalTo(MvcEvent::EVENT_ROUTE),
                $this->equalTo(array($listener, 'onRoute')),
                $this->lessThan(-99)
            );
        $listener->attach($events);
    }

    public function testReturnsEarlyIfRequestIsNonHttp()
    {
        $listener = new ContentValidationListener();

        $request = new StdlibRequest();
        $event   = new MvcEvent();
        $event->setRequest($request);

        $this->assertNull($listener->onRoute($event));
        $this->assertNull($event->getResponse());
    }

    public function nonBodyMethods()
    {
        return array(
            'get'     => array('GET'),
            'head'    => array('HEAD'),
            'options' => array('OPTIONS'),
            'delete'  => array('DELETE'),
        );
    }

    /**
     * @dataProvider nonBodyMethods
     */
    public function testReturnsEarlyIfRequestMethodWillNotContainRequestBody($method)
    {
        $listener = new ContentValidationListener();

        $request = new HttpRequest();
        $request->setMethod($method);
        $event   = new MvcEvent();
        $event->setRequest($request);

        $this->assertNull($listener->onRoute($event));
        $this->assertNull($event->getResponse());
    }

    public function testReturnsEarlyIfNoRouteMatchesPresent()
    {
        $listener = new ContentValidationListener();

        $request = new HttpRequest();
        $request->setMethod('POST');
        $event   = new MvcEvent();
        $event->setRequest($request);

        $this->assertNull($listener->onRoute($event));
        $this->assertNull($event->getResponse());
    }

    public function testReturnsEarlyIfRouteMatchesDoNotContainControllerService()
    {
        $listener = new ContentValidationListener();

        $request = new HttpRequest();
        $request->setMethod('POST');
        $matches = new RouteMatch(array());
        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);

        $this->assertNull($listener->onRoute($event));
        $this->assertNull($event->getResponse());
    }

    public function testReturnsEarlyIfControllerServiceIsNotInConfig()
    {
        $listener = new ContentValidationListener();

        $request = new HttpRequest();
        $request->setMethod('POST');
        $matches = new RouteMatch(array('controller' => 'Foo'));
        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);

        $this->assertNull($listener->onRoute($event));
        $this->assertNull($event->getResponse());
    }

    public function testReturnsApiProblemResponseIfContentNegotiationBodyDataIsMissing()
    {
        $services = new ServiceManager();
        $services->setService('FooValidator', new InputFilter());
        $listener = new ContentValidationListener(array(
            'Foo' => array('input_filter' => 'FooValidator'),
        ), $services);

        $request = new HttpRequest();
        $request->setMethod('POST');
        $matches = new RouteMatch(array('controller' => 'Foo'));
        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf('Laminas\ApiTools\ApiProblem\ApiProblemResponse', $response);
        return $response;
    }

    /**
     * @depends testReturnsApiProblemResponseIfContentNegotiationBodyDataIsMissing
     */
    public function testMissingContentNegotiationDataHas500Response($response)
    {
        $this->assertEquals(500, $response->getApiProblem()->status);
    }

    public function testReturnsApiProblemResponseIfInputFilterServiceIsInvalid()
    {
        $services = new ServiceManager();
        $listener = new ContentValidationListener(array(
            'Foo' => array('input_filter' => 'FooValidator'),
        ), $services);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = new RouteMatch(array('controller' => 'Foo'));

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf('Laminas\ApiTools\ApiProblem\ApiProblemResponse', $response);
        $this->assertEquals(500, $response->getApiProblem()->status);
    }

    public function testReturnsNothingIfContentIsValid()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter(array(
            'foo' => array(
                'name' => 'foo',
                'validators' => array(
                    array('name' => 'Digits'),
                ),
            ),
            'bar' => array(
                'name' => 'bar',
                'validators' => array(
                    array(
                        'name'    => 'Regex',
                        'options' => array('pattern' => '/^[a-z]+/i'),
                    ),
                ),
            ),
        )));
        $listener = new ContentValidationListener(array(
            'Foo' => array('input_filter' => 'FooValidator'),
        ), $services);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = new RouteMatch(array('controller' => 'Foo'));

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams(array(
            'foo' => 123,
            'bar' => 'abc',
        ));

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $this->assertNull($listener->onRoute($event));
        $this->assertNull($event->getResponse());
        return $event;
    }

    public function testReturnsApiProblemResponseIfContentIsInvalid()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter(array(
            'foo' => array(
                'name' => 'foo',
                'validators' => array(
                    array('name' => 'Digits'),
                ),
            ),
            'bar' => array(
                'name' => 'bar',
                'validators' => array(
                    array(
                        'name'    => 'Regex',
                        'options' => array('pattern' => '/^[a-z]+/i'),
                    ),
                ),
            ),
        )));
        $listener = new ContentValidationListener(array(
            'Foo' => array('input_filter' => 'FooValidator'),
        ), $services);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = new RouteMatch(array('controller' => 'Foo'));

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams(array(
            'foo' => 'abc',
            'bar' => 123,
        ));

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf('Laminas\ApiTools\ApiProblem\ApiProblemResponse', $response);
        return $response;
    }

    /**
     * @depends testReturnsApiProblemResponseIfContentIsInvalid
     */
    public function testApiProblemResponseFromInvalidContentHas422Status($response)
    {
        $this->assertEquals(422, $response->getApiProblem()->status);
    }

    /**
     * @depends testReturnsApiProblemResponseIfContentIsInvalid
     */
    public function testApiProblemResponseFromInvalidContentContainsValidationErrorMessages($response)
    {
        $problem = $response->getApiProblem();
        $asArray = $problem->toArray();
        $this->assertArrayHasKey('validation_messages', $asArray);
        $this->assertCount(2, $asArray['validation_messages']);
        $this->assertArrayHasKey('foo', $asArray['validation_messages']);
        $this->assertInternalType('array', $asArray['validation_messages']['foo']);
        $this->assertArrayHasKey('bar', $asArray['validation_messages']);
        $this->assertInternalType('array', $asArray['validation_messages']['bar']);
    }

    public function testReturnsApiProblemResponseIfParametersAreMissing()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter(array(
            'foo' => array(
                'name' => 'foo',
                'validators' => array(
                    array('name' => 'Digits'),
                ),
            ),
            'bar' => array(
                'name' => 'bar',
                'validators' => array(
                    array(
                        'name'    => 'Regex',
                        'options' => array('pattern' => '/^[a-z]+/i'),
                    ),
                ),
            ),
        )));
        $listener = new ContentValidationListener(array(
            'Foo' => array('input_filter' => 'FooValidator'),
        ), $services);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = new RouteMatch(array('controller' => 'Foo'));

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams(array(
            'foo' => 123,
        ));

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf('Laminas\ApiTools\ApiProblem\ApiProblemResponse', $response);
        return $response;
    }

    public function testAllowsValidationOfPartialSetsForPatchRequests()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter(array(
            'foo' => array(
                'name' => 'foo',
                'validators' => array(
                    array('name' => 'Digits'),
                ),
            ),
            'bar' => array(
                'name' => 'bar',
                'validators' => array(
                    array(
                        'name'    => 'Regex',
                        'options' => array('pattern' => '/^[a-z]+/i'),
                    ),
                ),
            ),
        )));
        $listener = new ContentValidationListener(array(
            'Foo' => array('input_filter' => 'FooValidator'),
        ), $services);

        $request = new HttpRequest();
        $request->setMethod('PATCH');

        $matches = new RouteMatch(array('controller' => 'Foo'));

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams(array(
            'foo' => 123,
        ));

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $this->assertNull($listener->onRoute($event));
    }

    public function testFailsValidationOfPartialSetsForPatchRequestsThatIncludeUnknownInputs()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter(array(
            'foo' => array(
                'name' => 'foo',
                'validators' => array(
                    array('name' => 'Digits'),
                ),
            ),
            'bar' => array(
                'name' => 'bar',
                'validators' => array(
                    array(
                        'name'    => 'Regex',
                        'options' => array('pattern' => '/^[a-z]+/i'),
                    ),
                ),
            ),
        )));
        $listener = new ContentValidationListener(array(
            'Foo' => array('input_filter' => 'FooValidator'),
        ), $services);

        $request = new HttpRequest();
        $request->setMethod('PATCH');

        $matches = new RouteMatch(array('controller' => 'Foo'));

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams(array(
            'foo' => 123,
            'baz' => 'who cares?',
        ));

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf('Laminas\ApiTools\ApiProblem\ApiProblemResponse', $response);
        return $response;
    }

    /**
     * @depends testFailsValidationOfPartialSetsForPatchRequestsThatIncludeUnknownInputs
     */
    public function testInvalidValidationGroupIs400Response($response)
    {
        $this->assertEquals(400, $response->getApiProblem()->status);
    }

    /**
     * @depends testReturnsNothingIfContentIsValid
     */
    public function testInputFilterIsInjectedIntoMvcEvent($event)
    {
        $inputFilter = $event->getParam('Laminas\ApiTools\ContentValidation\InputFilter');
        $this->assertInstanceOf('Laminas\InputFilter\InputFilter', $inputFilter);
    }

    /**
     * @group api-tools-skeleton-43
     */
    public function testPassingOnlyDataNotInInputFilterShouldInvalidateRequest()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter(array(
            'first_name' => array(
                'name' => 'first_name',
                'required' => true,
                'validators' => array(
                    array(
                        'name' => 'Laminas\Validator\NotEmpty',
                        'options' => array('breakchainonfailure' => true),
                    ),
                ),
            ),
        )));
        $listener = new ContentValidationListener(array(
            'Foo' => array('input_filter' => 'FooValidator'),
        ), $services);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = new RouteMatch(array('controller' => 'Foo'));

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams(array(
            'foo' => 'abc',
            'bar' => 123,
        ));

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf('Laminas\ApiTools\ApiProblem\ApiProblemResponse', $response);
        return $response;
    }

    public function httpMethodSpecificInputFilters()
    {
        return array(
            'post-valid' => array(
                'POST',
                array('post' => 123),
                true,
                'PostValidator',
            ),
            'post-invalid' => array(
                'POST',
                array('post' => 'abc'),
                false,
                'PostValidator',
            ),
            'post-invalid-property' => array(
                'POST',
                array('foo' => 123),
                false,
                'PostValidator',
            ),
            'patch-valid' => array(
                'PATCH',
                array('patch' => 123),
                true,
                'PatchValidator',
            ),
            'patch-invalid' => array(
                'PATCH',
                array('patch' => 'abc'),
                false,
                'PatchValidator',
            ),
            'patch-invalid-property' => array(
                'PATCH',
                array('foo' => 123),
                false,
                'PatchValidator',
            ),
            'put-valid' => array(
                'PUT',
                array('put' => 123),
                true,
                'PutValidator',
            ),
            'put-invalid' => array(
                'PUT',
                array('put' => 'abc'),
                false,
                'PutValidator',
            ),
            'put-invalid-property' => array(
                'PUT',
                array('foo' => 123),
                false,
                'PutValidator',
            ),
        );
    }

    public function configureInputFilters($services)
    {
        $inputFilterFactory = new InputFilterFactory();
        $services->setService('PostValidator', $inputFilterFactory->createInputFilter(array(
            'post' => array(
                'name' => 'post',
                'required' => true,
                'validators' => array(
                    array('name' => 'Digits'),
                ),
            ),
        )));

        $services->setService('PatchValidator', $inputFilterFactory->createInputFilter(array(
            'patch' => array(
                'name' => 'patch',
                'required' => true,
                'validators' => array(
                    array('name' => 'Digits'),
                ),
            ),
        )));

        $services->setService('PutValidator', $inputFilterFactory->createInputFilter(array(
            'put' => array(
                'name' => 'put',
                'required' => true,
                'validators' => array(
                    array('name' => 'Digits'),
                ),
            ),
        )));
    }

    /**
     * @group method-specific
     * @dataProvider httpMethodSpecificInputFilters
     */
    public function testCanFetchHttpMethodSpecificInputFilterWhenValidating($method, array $data, $expectedIsValid, $filterName)
    {
        $services = new ServiceManager();
        $this->configureInputFilters($services);

        $listener = new ContentValidationListener(array(
            'Foo' => array(
                'POST'  => 'PostValidator',
                'PATCH' => 'PatchValidator',
                'PUT'   => 'PutValidator',
            ),
        ), $services);

        $request = new HttpRequest();
        $request->setMethod($method);

        $matches = new RouteMatch(array('controller' => 'Foo'));

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($data);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $result = $listener->onRoute($event);

        // Ensure input filter discovered is the same one we expect
        $inputFilter = $event->getParam('Laminas\ApiTools\ContentValidation\InputFilter');
        $this->assertInstanceOf('Laminas\InputFilter\InputFilterInterface', $inputFilter);
        $this->assertSame($services->get($filterName), $inputFilter);

        // Ensure we have a response we expect
        if ($expectedIsValid) {
            $this->assertNull($result);
            $this->assertNull($event->getResponse());
        } else {
            $this->assertInstanceOf('Laminas\ApiTools\ApiProblem\ApiProblemResponse', $result);
        }
    }
}
