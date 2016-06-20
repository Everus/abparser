<?php

namespace Everus\Service;

use Everus\Model\Category;
use Everus\Model\Contact;
use Doctrine\Common\Collections\ArrayCollection;

class Site
{
  const SITE_URL = 'http://atyrau-business.com/';
  private $cat_repo;
  private $con_repo;
  private $em;
  private $crawler;

  public function setEntityManager($em)
  {
    $this->em = $em;
    $this->cat_repo = $em->getRepository('Everus\Model\Category');
    $this->con_repo = $em->getRepository('Everus\Model\Contact');
    return $this;
  }

  public function setCrawler($crawler)
  {
    $this->crawler = $crawler;
    return $this;
  }

  public function getContact($url)
  {
    $contact = $this->con_repo->findOneBy(['url' => $url]);
    return (null === $contact) ? $this->getContactFromWeb($url) : $contact;
  }

  private function getContactFromWeb($url)
  {
    $crawler = $this->crawler->request('GET', $url);
    $contact = new Contact();
    $contact->setUrl($url);
    $contact->setName($crawler->filter('div#dle-content > h3')->first()->text());
    $description = $crawler->filter('div.story.mb10')->first()->text();
    $contact->setDescription($description);
    $contact->setPhone($this->extractPhones($description));
    $contact->setEmail($this->extractEmails($description));
    $this->em->persist($contact);
    $this->em->flush();
    return $contact;
  }

  public function extractEmails($text)
  {
    $emails = [];
    $result = preg_match_all(
      '/(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){255,})(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){65,}@)(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22))(?:\.(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-[a-z0-9]+)*\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-[a-z0-9]+)*)|(?:\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\]))/',
      $text,
      $emails);
    if($result) {
      $emails = $emails[0];
      return implode(', ', $emails);
    } else {
      return null;
    }
  }

  public function extractPhones($text)
  {
    $phones = [];
    $result = preg_match_all("/((8|\+7)[\- ]?)?(\(?\d{3}\)?[\- ]?)?[\d\- ]{6,10}/", $text, $phones);
    if($result) {
      $phones = array_map(function($item) {
        return preg_replace("/[^\d+]/", "", $item);
      }, $phones[0]);
      return implode(', ', $phones);
    } else {
      return null;
    }
  }

  public function getCategories()
  {
    $categories = $this->cat_repo->findAll();
    $categories = $this->addNewCategoriesFromWeb(new ArrayCollection($categories));
    return $categories;
  }

  private function addNewCategoriesFromWeb(ArrayCollection $categories)
  {
    $em = $this->em;
    $crawler = $this->crawler->request('GET', self::SITE_URL);
    $newCategories = $crawler
      ->filter('ul.nav.sub-nav > li > a')
      ->reduce(
        function($node, $i) use($categories) {
          return !$categories->exists(
            function($key, $element) use($node) {
              return $element->getUrl() === $node->link()->getUri();
            }
          );
        })
      ->each(function($node) use($em) {
        $cat = new Category();
        $cat->setUrl($node->link()->getUri());
        $cat->setName($node->text());
        $em->persist($cat);
        return $cat;
      });
    $em->flush();
    foreach ($newCategories as $key => $value) {
      $categories->add($value);
    }
    return $categories;
  }

  public function getContacts($category)
  {
    $crawler = $this->crawler->request('GET', $category->getUrl());
    $pages = $this->getPages($crawler);
    $contacts = $this->extractContacts($crawler);
    if($pages !== null) {
      foreach ($pages as $url) {
        $page_crawler = $this->crawler->request('GET', $url);
        $contacts = array_merge($contacts, $this->extractContacts($page_crawler));
      }
    }
    foreach ($contacts as $contact) {
      $contact->setCategory($category);
      $this->em->persist($contact);
      $this->em->flush();
    }
    return $contacts;
  }

  private function extractContacts($crawler)
  {
    $urls = $crawler->filter('div#dle-content > h3 > a')->each(function($node) {
      return $node->link()->getUri();
    });
    $contacts = [];
    foreach ($urls as $url) {
      $contacts[] = $this->getContact($url);
    }
    return $contacts;
  }

  private function getPages($crawler)
  {
    $pages = $crawler->filter('div.navigation >  a');
    if($pages->count() === 0) {
      return null;
    }
    return array_unique($pages->each((function($node) {
      return $node->link()->getUri();
    })));
  }
}
