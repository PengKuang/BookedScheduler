<?php
/**
Copyright 2013 Nick Korbel

This file is part of phpScheduleIt.

phpScheduleIt is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

phpScheduleIt is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with phpScheduleIt.  If not, see <http://www.gnu.org/licenses/>.
 */

interface IScheduleResourceFilter
{
	/**
	 * @param BookableResource[] $resources
	 * @param IResourceRepository $resourceRepository
	 * @param IAttributeService $attributeService
	 * @return int[] filtered resource ids
	 */
	public function FilterResources($resources, IResourceRepository $resourceRepository, IAttributeService $attributeService);
}

class ScheduleResourceFilter implements IScheduleResourceFilter
{
	public $ScheduleId;
	public $ResourceId;
	public $GroupId;
	public $ResourceTypeId;
	public $MinCapacity;
	public $ResourceAttributes;
	public $ResourceTypeAttributes;

	/**
	 * @param int|null $scheduleId
	 * @param int|null $resourceTypeId
	 * @param int|null $minCapacity
	 * @param AttributeValue[]|null $resourceAttributes
	 * @param AttributeValue[]|null $resourceTypeAttributes
	 */
	public function __construct($scheduleId = null,
								$resourceTypeId = null,
								$minCapacity = null,
								$resourceAttributes = null,
								$resourceTypeAttributes = null)
	{
		$this->ScheduleId = $scheduleId;
		$this->ResourceTypeId = $resourceTypeId;
		$this->MinCapacity = empty($minCapacity) ? null : $minCapacity;
		$this->ResourceAttributes = empty($resourceAttributes) ? array() : $resourceAttributes;
		$this->ResourceTypeAttributes = empty($resourceTypeAttributes) ? array() : $resourceTypeAttributes;
	}

	public static function FromCookie($val)
	{
		return new ScheduleResourceFilter($val->ScheduleId, $val->ResourceTypeId, $val->MinCapacity);
	}

	private function HasFilter()
	{
		return !empty($this->ResourceId) || !empty($this->GroupId) || !empty($this->ResourceTypeId) || !empty($this->MinCapacity) || !empty($this->ResourceAttributes) || !empty($this->ResourceTypeAttributes);
	}

	public function FilterResources($resources, IResourceRepository $resourceRepository, IAttributeService $attributeService)
	{
		$resourceIds = array();

		if (!$this->HasFilter())
		{
			foreach ($resources as $resource)
			{
				$resourceIds[] = $resource->GetId();
			}

			return $resourceIds;
		}

		$groupResourceIds = array();
		if (!empty($this->GroupId) && empty($this->ResourceId))
		{
			$groups = $resourceRepository->GetResourceGroups($this->ScheduleId);
			$groupResourceIds = $groups->GetResourceIds($this->GroupId);
		}

		$resourceAttributeValues = null;
		if(!empty($this->ResourceAttributes))
		{
			$resourceAttributeValues = $attributeService->GetAttributes(CustomAttributeCategory::RESOURCE, null);
		}

		$resourceIds = array();

		foreach ($resources as $resource)
		{
			$resourceIds[] = $resource->GetId();

			if (!empty($this->ResourceId) && $resource->GetId() != $this->ResourceId)
			{
				array_pop($resourceIds);
				continue;
			}

			if (!empty($this->GroupId) && !in_array($resource->GetId(), $groupResourceIds))
			{
				array_pop($resourceIds);
				continue;
			}

			if (!empty($this->MinCapacity) && $resource->GetMaxParticipants() < $this->MinCapacity)
			{
				array_pop($resourceIds);
				continue;
			}

			if (!empty($this->ResourceTypeId) && $resource->GetResourceTypeId() != $this->ResourceTypeId)
			{
				array_pop($resourceIds);
				continue;
			}

			if (!empty($this->ResourceAttributes))
			{
				$values = $resourceAttributeValues->GetAttributes($resource->GetId());

				/** var @attribute AttributeValue */
				foreach($this->ResourceAttributes as $attribute)
				{
					$value = $this->GetAttribute($values, $attribute->AttributeId);
					if ($value == null || $value->Value() != $attribute->Value)
					{
						array_pop($resourceIds);
						break;
//						continue;
					}
				}
			}

		}

		return $resourceIds;
	}

	/**
	 * @param Attribute[] $attributes
	 * @param int $attributeId
	 * @return null|Attribute
	 */
	private function GetAttribute($attributes, $attributeId)
	{
		foreach ($attributes as $attribute)
		{
			if ($attribute->Id() == $attributeId)
			{
				return $attribute;
			}
		}
		return null;
	}
}

?>