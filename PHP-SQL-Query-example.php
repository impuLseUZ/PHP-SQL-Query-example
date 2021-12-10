<?php
//Примечение:
//Ниже приведенные запросы в основном связаны с движком OpenCart 3.x с интеграцией телеграм бота на php

//Задача: Нужно было интегрировать заказ поступающий из телеграм бота к базе с движком OpenCart 3.x и хранить поступающий заказ в отдельной таблице(orders_id)
//Решение: Так-как заполняющиеся поля в таблице(orders_id) связаны с несколькими таблицами,
//         приходилось сделать несколько запросов к разным таблицам, 
//		   а для оптимизации нужно было сделать все одном запросе дабы не нагружать Базу и код.
//		   из-за этого пришлось сделать внутри INSERT связать несколько (SELECT) на выборку запросов
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
	
	
//Задача: Нужно было сделать функцию, когда в поиске телеграм бота пользователь напишет ключевые слова для поиска продукта, 
//	а функция возвращал подходящие данные о продуктах
//Решение: Для решение этой задачи я использовал оператор LIKE, 
//		   потому что этот оператор используется для поиска данных, похожих на определённый образец. 
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


//Задача: Нужно было сделать функцию, который возвращает топ 10 полулярных заказов за последний 10 дней для телеграм бота который интегрирован с OpenCart 3.x. 
//Решение: Группировал по ид продуктов(product_id) и суммировал количество заказа(quantity) с интервалом 10 дней и сортировал по убиванию (DESC)
public function TopProductsOrder()
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