<?php
//Примечение:
//Ниже приведенные запросы в основном связаны с движком OpenCart 3.x с интеграцией телеграм бота на php

/*

Нужно было интегрировать заказ поступающий из телеграмм бота к базе сайта интернет магазина на движке OpenCart 3.x и хранить поступающий заказ в отдельной таблице(orders_id)

Задача подходит для решений создания новой записи в таблице, и к этой записи заполняющиеся поля нужно взять по user_id  из разных других таблиц что бы всё было в одном запросе.

Причины, по которым я пришёл к этому решению.Изначально в коде я делал несколько (SELECT) на выборку запросов и полученные данные я сохранял в переменных, 
а после я вставлял эти переменные в создании новой записи. По этой причине я терял время и нагружал базу данных с запросами.
Пришлось искать другой путь к оптимизации запроса и решение было таковым.  Решил сделать внутри (INSERT) создающиеся записи связать несколько (SELECT) на выборку запросов
В итоге благодаря этому решению утраченное время заметно сократилось и оптимизировался запрос к данным.

*/

public function InsertNewUserOrder($user_id=0, $language_id=0, $order_id=0)
    {
		$sql = "INSERT INTO orders_id (userID,orders_type,comments, adress,latitude,longitude,order_time,order_id,tg_order_id,amount) 
			SELECT
			( SELECT userID FROM users WHERE userID = '".$user_id."' LIMIT 1) as tuserID,
			( SELECT type_payment FROM users_basket WHERE userID = '".$user_id."' AND status='1' LIMIT 1) as type_pay,
			( SELECT order_comments FROM users WHERE userID = '".$user_id."' LIMIT 1) as tcom,
			( SELECT adress_text FROM users WHERE userID = '".$user_id."' LIMIT 1) as tad,
			( SELECT latitude FROM users WHERE userID = '".$user_id."' LIMIT 1) as tlat,
			( SELECT longitude FROM users WHERE userID = '".$user_id."' LIMIT 1) as tlon,
			( SELECT delivery_time FROM users WHERE userID = '".$user_id."' LIMIT 1) as tdel,
			".$order_id.",
			( SELECT order_id FROM users_basket WHERE userID = '".$user_id."' AND status='1' LIMIT 1) as tid,
			( SELECT SUM(t.quantity*m.price) as summa FROM users_basket t
				LEFT JOIN oc_product m ON m.product_id = t.product_id
				LEFT JOIN oc_product_description d ON d.product_id  = t.product_id and d.language_id=".$language_id."
				WHERE t.userID= :userID AND t.status=1
			) as itogs";		
		
		
		$stmt = self::$DB->prepare($sql);
		$stmt->execute(['userID' => $user_id]);
		
	}
	
/*	
Нужно было сделать функцию в разделе поиска продуктов телеграмм бота, который интегрируется с сайтом интернет магазина на движке OpenCart 3.х, 
при написании ключевых слов в ответ получает к этим ключевым словам максимально похожие продукты.

Задача подходит для решений поиска совпадений значений в ячейках таблиц с заданной строкой или шаблоном.

Причины, по которым я пришёл к этому решению. Я начал искать разные возможности SQL и нашёл такого оператора как LIKE. 
Этот оператор максимально подходить к решению моей задачи. Потому-что этот оператор используется для поиска данных, похожих на определённый образец.
*/

public function SearchProduct($product_name = '') : ? array
   {
		$name = "%$product_name%";
		$sql ="SELECT a.product_id,a.name,b.price,b.image,b.manufacturer_id,c.name as shopname
		FROM oc_product_description a
		LEFT JOIN oc_product b ON b.product_id=a.product_id
		LEFT JOIN oc_manufacturer c ON c.manufacturer_id=b.manufacturer_id
		WHERE a.name LIKE ? AND b.status= 1 LIMIT 5";
	
		$stmt = self::$DB->prepare($sql);
		$stmt->execute([$name]);
		return $stmt->fetchAll();
		
	}	

/*
Нужно было сделать функцию для телеграмм бота, который интегрирован с сайтом интернет магазина на движке OpenCart 3.х, 
при нажатии кнопки топ популярных заказов моментально отображались топ 10 популярных заказов за последние 10 дней.

Задача подходит для решений поиска самых востребованных заказов (просмотренные товары и т.п.) с интервалом 10 дней.

Причины, по которым я пришёл к этому решению. Нужно было сперва сгруппировать продукты по ID-продуктов (product_id) и 
суммировать количество (quantity) заказов. Для группирования продуктов использовал (GROUP BY),  
а для сортировки количество (ORDER BY) и сортировка по убыванию (DESC) , 
а после чтобы взять текущий момент времени, я использовал функцию (NOW) и 
что бы отнимать от даты определенные промежутки времени использовал команду INTERVAL.
*/
public function TopOrderProducts()
	{	
		$sql ="SELECT a.product_id,SUM(a.quantity) as total,t.name,a.date
			FROM users_basket  a
			LEFT JOIN oc_product_description t ON t.product_id=a.product_id
			WHERE a.product_id>0 and t.language_id=1 and a.date > NOW() - INTERVAL 10 DAY and a.date < NOW() and a.status=2 
			GROUP BY a.product_id ORDER BY total DESC LIMIT 10";
		
		$stmt = self::$DB->prepare($sql);
		$stmt->execute();
		return $stmt->fetchAll();
	
	}	
?>