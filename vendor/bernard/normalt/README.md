Normalt
=======

[![Build Status](https://travis-ci.org/bernardphp/normalt.png?branch=master)](https://travis-ci.org/bernardphp/normalt)

Normalt contains additional normalizers for use with the serializer component found in Symfony. It also
implements a normalizer delegator that will look at the data you want normalized and/or denormalized
and call the normalizer which supports it.

In the context of Normalt normalization is the act of converting an object into an array. Denormalization
is the opposite direction (converting array into an object). This is to my knowledge the same concept
Symfony serializer uses.


Table of Contents
-----------------

 * [Getting Started](#getting-started)
 * [Normalizers](#normalizers)
   * [AggregateNormalizer](#aggregatenormalizer)
   * [DoctrineNormalizer](#doctrinenormalizer)
   * [RecursiveReflectionNormalizer](#recursivereflectionnormalizer)
 * [License](#license)


Getting Started
---------------

Getting started is as easy as requiring the library with composer.

``` bash
$ composer require bernard/normalt
```


Normalizers
-----------

Theese normalizers can be used with the serializer component directly or through the `AggregateNormalizer`.


### AggregateNormalizer

`AggregateNormalizer` is a delegator and aggregator as it aggregates multiple normalizers and denormalizers
which it will delegate the process to.

It have a list of normalizers and denormalizers. It will ask each of theese if they support the data/object
and use the first found.

It implements a subset of the full serializer and its only focus is normalizing to arrays and
denormalize arrays into objects. This lets you focus on normalization instead of converting
into a specific format such as `xml`, `json` etc.


#### Usage

You need to instantiate the normalizer and the list of normalizer/denormalizers you want to use.
For this example we use `GetSetMethodNormalizer` which is distributed with the symfony package.

This is the class we are going to use. `GetSetMethodNormalizer` uses getters and setters to do
its job.

``` php
class User
{
    protected $name;

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }
}
```

Lets normalize and denormalize it again.

``` php
use Normalt\Normalizer\AggregateNormalizer;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;

$aggregate = new AggregateNormalizer([new GetSetMethodNormalizer]);

$user = new User;
$user->setName('henrik');

$array = $aggregate->normalize($user);

// $array now contains ['name' => 'henrik']

$user = $aggregate->denormalize($array, 'User');
echo $user->getName(); // outputs Henrik
```

In contrast to the other normalizers in this package, it does __not__ make sense to use this with the 
serializer as the Serializer already does most of the functionality already.


### DoctrineNormalizer

`DoctrineNormalizer` normalizes mapped objects (Entities, Documents etc.) into arrays and back again.

It usage is very simple. The following example assume a mapped object of `$user` and that you are
using the doctrine orm (other doctrine projects work aswell!).

``` php
use Doctrine\ORM\EntityManager;
use Normalt\Normalizer\DoctrineNormalizer;

// create $entityManager
$normalizer = new DoctrineNormalizer($entityManager);

// assuming $user is a mapped object and have the identifier value of 10. the following will return
// array('MyModel\User', 10)
$array = $normalizer->normalize($user);

// using the same structure you can convert it back into a user
$user = $normalizer->denormalize($array, null);
```


### RecursiveReflectionNormalizer

This normalizes also delegates like the `AggregateNormalizer`, but delegates for each property in the object
you are normalizing to normalize. It does this with recursion, so if a normalizer does not support a given
property and is an array it will loop through that array and look for more objects.

The same thing happens when denormalizing, except it will try and find a supporting denormalizer for the
property structure before looping.

Using is simple as the other, the example utilises `DoctrineNormalizer` and assumes we have a `$profile` object
that contains a reference to a user with `$profile->user`.

``` php
use Normalt\Normalizer\RecursiveReflectionNormalizer;
use Normalt\Normalizer\DoctrineNormalizer;

$normalizer = new RecursiveReflectionNormalizer([new DoctrineNormalizer($entityManager)]);

// following will return assuming User is mapped and has the identifier of 10
//['user' => ['MyModel\User', 10]]
$array = $normalizer->normalize($profile);

// converting it back into the object.
// $profile->user is now an instance of MyModel\User
$profile = $normalize->denormalize($array, 'MyModel\Profile');
```


License
-------

Please refer to the included `LICENSE` file.
