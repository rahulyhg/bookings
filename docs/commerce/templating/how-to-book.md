---
title: How to Book
---

# How to Book

Note `options[ticketDate]` can be any valid [DateTime value](http://php.net/manual/en/datetime.formats.php).

e.g. `<input type="text" name="options[ticketDate]" value="2018-11-14 15:38:00">`

## Add Single

```twig
<form method="post">
    {{ csrfInput() }}
    <input type="hidden" name="action" value="commerce/cart/update-cart" />
    
    {% set variant = product.variants.one() %}
    <input type="hidden" name="purchasableId" value="{{ variant.id }}">
    <input type="hidden" name="options[ticketId]" value="{{ variant.ticket.id }}">
    <input type="number" name="qty" value="1">
    <input type="date" name="options[ticketDate][date]">
    <input type="time" name="options[ticketDate][time]">
    <input type="hidden" name="options[ticketDate][timezone]" value="{{ craft.app.getTimeZone() }}">
    
    <button>Add to Cart</button>
</form>
```

## Add Multiple

```twig
<form method="post">
    {{ csrfInput() }}
    <input type="hidden" name="action" value="commerce/cart/update-cart" />
    
    <ul>
        {% for variant in product.variants %}
            <li>
                <p><strong>{{ variant.title }}</strong> - {{ variant.price|currency }}</p>
                <input type="hidden" name="purchasables[{{loop.index}}][id]" value="{{ variant.id }}" />
                <input type="hidden" name="purchasables[{{loop.index}}][options][ticketId]" value="{{ variant.myTicketField.id }}" />
                <input type="date" name="purchasables[{{loop.index}}][options][ticketDate][date]" />
                <input type="time" name="purchasables[{{loop.index}}][options][ticketDate][time]" />
                <input type="hidden" name="purchasables[{{loop.index}}][options][ticketDate][timezone]" value="{{ craft.app.getTimeZone() }}" />
                <input type="number" name="purchasables[{{loop.index}}][qty]" value="1" />
            </li>
        {% endfor %}
    </ul>
    
    <button>Add to Cart</button>
</form>
```

## Flexible

```twig
<form method="post">
	{{ csrfInput() }}
	<input type="hidden" name="action" value="commerce/cart/update-cart" />
	
	{% set variant = product.defaultVariant %}
	<p><strong>{{ variant.title }}</strong> - {{ variant.price|currency }}</p>
	<input type="hidden" name="purchasables[0][id]" value="{{ variant.id }}"/>
	<input type="hidden" name="purchasables[0][qty]" value="1"/>
	<input type="hidden" name="purchasables[0][options][ticketId]" value="{{ variant.ticket.id }}"/>
	<input type="hidden" name="purchasables[0][options][ticketDate]"/>
	<label>
		Start
		<input type="text" name="purchasables[0][options][ticketDate][start]"/>
	</label>
	<br>
	<label>
		End
		<input type="text" name="purchasables[0][options][ticketDate][end]"/>
	</label>
	
	<button>Add to Cart</button>
</form>
```
