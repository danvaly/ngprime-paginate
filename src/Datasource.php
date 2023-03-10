<?php

namespace Danvaly\PrimeDatasource;

class Datasource
{
    private $data;
    private $total = 0;
    private $per_page = 0;
    private $current_page = 1;
    private $last_page = null;
    private $first_item = null;
    private $last_item = null;
    private $has_more_pages = false;
    private $next_page_url = null;
    private $prev_page_url = null;
    private $from = 0;
    private $to = 0;
    private $options = [];


    public function __construct($data)
    {
        $this->data = $data;
    }

    public function count()
    {
        return count($this->data);
    }

    public function first()
    {
        return reset($this->data);
    }

    public function last()
    {
        return end($this->data);
    }

    public function hasMorePages()
    {
        return $this->has_more_pages;
    }

    public function currentPage()
    {
        return $this->current_page;
    }

    public function lastPage()
    {
        return $this->last_page;
    }

    public function nextPageUrl()
    {
        return $this->next_page_url;
    }

    public function prevPageUrl()
    {
        return $this->prev_page_url;
    }

    public function perPage()
    {
        return $this->per_page;
    }


    public function total()
    {
        return $this->total;
    }

    public function firstItem()
    {
        return $this->first_item;
    }

    public function lastItem()
    {
        return $this->last_item;
    }

    public function from()
    {
        return $this->from;
    }

    public function toArray()
    {
        return [
            'data' => $this->data,
            'total' => $this->total,
            'per_page' => $this->per_page,
            'current_page' => $this->current_page,
            'last_page' => $this->last_page,
            'first_item' => $this->first_item,
            'last_item' => $this->last_item,
            'has_more_pages' => $this->has_more_pages,
            'next_page_url' => $this->next_page_url,
            'prev_page_url' => $this->prev_page_url,
            'from' => $this->from,
            'to' => $this->to,
            'options' => $this->options,
        ];
    }

    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }


    public function __get($name)
    {
        return $this->$name;
    }

    public function __set($name, $value)
    {
        $this->$name = $value;
    }

    public function __isset($name)
    {
        return isset($this->$name);
    }

    public function __unset($name)
    {
        unset($this->$name);
    }

}